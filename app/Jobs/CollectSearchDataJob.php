<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Models\SiteAudit;
use App\Services\Audit\Unchecked;
use App\Services\Integrations\GoogleOAuth;
use App\Services\Integrations\SearchConsoleClient;
use App\Services\Integrations\WebmasterClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Данные панелей вебмастера: Яндекс.Вебмастер и Google Search Console.
 *
 * Оба сервиса отдают информацию только владельцу ресурса, поэтому этап зависит
 * не от кода, а от того, привязан ли внешний ресурс к домену. Не привязан или
 * доступ отозван — пишем «не проверено» с причиной, а не молчим.
 */
final class CollectSearchDataJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Позиции с 11-й по 20-ю: показы уже есть, кликов почти нет — ближний резерв. */
    private const STRIKING_FROM = 10.5;

    private const STRIKING_TO = 20.5;

    /** Меньше стольких показов — судить не о чем. */
    private const MIN_IMPRESSIONS = 50;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        Unchecked $unchecked,
        GoogleOAuth $google,
    ): void {
        $audit = $audits->findById($this->auditId);
        $audit->loadMissing(['project.organization', 'domain']);

        $metrics = [];
        $findings = [];

        $this->webmaster($audit, $unchecked, $metrics, $findings);
        $this->searchConsole($audit, $unchecked, $google, $metrics, $findings);

        if ($metrics === []) {
            return;
        }

        // Список находок перечитываем перед самой записью и дописываем свои.
        // Прочитанный в начале — уже устарел: соседние джобы батча пишут туда же,
        // и запись старого списка стирала их находки.
        $fresh = $audits->findById($audit->id);

        $kept = array_values(array_filter(
            $fresh->findings ?? [],
            static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'search.'),
        ));

        $audits->update($fresh, [
            'findings' => [...$kept, ...$findings],
            'metrics' => [...($fresh->metrics ?? []), ...$metrics],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function webmaster(SiteAudit $audit, Unchecked $unchecked, array &$metrics, array &$findings): void
    {
        $hostId = $audit->domain?->webmaster_host_id;

        if ($hostId === null || $hostId === '') {
            $unchecked->record($audit, 'webmaster', 'Сайт не сопоставлен с ресурсом в Яндекс.Вебмастере');

            return;
        }

        $client = new WebmasterClient((string) ($audit->project->organization->yandex_token ?? ''));

        if (! $client->available()) {
            $unchecked->record($audit, 'webmaster', 'Нет OAuth-доступа к Яндексу у организации');

            return;
        }

        $summary = $client->summary($hostId);

        if ($summary === null) {
            $unchecked->record($audit, 'webmaster', 'Вебмастер не ответил — проверьте право webmaster:hostinfo у приложения');

            return;
        }

        $indexing = $client->indexing($hostId);
        $queries = $client->searchQueries($hostId) ?? [];

        $metrics['webmaster'] = [
            'sqi' => $summary['sqi'],
            'problems' => $summary['problems'],
            'in_search' => $indexing['in_search'] ?? null,
            'changed_by' => $indexing['changed_by'] ?? null,
            'top_queries' => array_slice($queries, 0, 50),
        ];

        // Фатальные и критичные проблемы Яндекс называет сам — не пересказываем своими словами.
        $serious = (int) ($summary['problems']['FATAL'] ?? 0) + (int) ($summary['problems']['CRITICAL'] ?? 0);

        if ($serious > 0) {
            $findings[] = [
                'check' => 'search.webmaster.problems',
                'code' => 'search.webmaster.problems',
                'category' => 'technical',
                'severity' => 'critical',
                'message' => 'Яндекс.Вебмастер сообщает о критичных проблемах сайта',
                'value' => $summary['problems'],
                'expected' => 'нет фатальных и критичных',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function searchConsole(
        SiteAudit $audit,
        Unchecked $unchecked,
        GoogleOAuth $google,
        array &$metrics,
        array &$findings,
    ): void {
        $site = $audit->domain?->search_console_site;

        if ($site === null || $site === '') {
            $unchecked->record($audit, 'search_console', 'Сайт не сопоставлен с ресурсом в Search Console');

            return;
        }

        $org = $audit->project->organization;
        $token = $google->accessToken($org);

        if ($token === null) {
            $unchecked->record($audit, 'search_console', 'Нет доступа Google у организации либо истёк refresh-токен');

            return;
        }

        $client = new SearchConsoleClient($token);
        $summary = $client->summary($site);

        if ($summary === null) {
            $unchecked->record($audit, 'search_console', 'Search Console не отдал данные по ресурсу');

            return;
        }

        $queries = $client->breakdown($site, 'query') ?? [];

        $metrics['search_console'] = [
            'summary' => $summary,
            'top_queries' => array_slice($queries, 0, 50),
        ];

        // Запросы во второй десятке: показы есть, кликов почти нет — самый дешёвый рост.
        $striking = array_values(array_filter(
            $queries,
            static fn (array $row): bool => (float) $row['position'] > self::STRIKING_FROM
                && (float) $row['position'] < self::STRIKING_TO
                && (float) $row['impressions'] >= self::MIN_IMPRESSIONS,
        ));

        if ($striking !== []) {
            $findings[] = [
                'check' => 'search.console.striking_distance',
                'code' => 'search.console.striking_distance',
                'category' => 'content',
                'severity' => 'notice',
                'message' => 'Запросы во второй десятке — ближний резерв роста',
                'value' => array_slice($striking, 0, 25),
                'expected' => 'вывести в топ-10',
            ];
        }
    }
}
