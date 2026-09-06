<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Models\PageAuditResult;
use App\Services\Audit\MetrikaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Поведенческие данные из Метрики: сводка по сайту, тайминги и проблемные
 * страницы. Один заход на прогон, не на страницу.
 *
 * Без токена и счётчика этап молчит — и это «данных нет», а не «всё хорошо».
 */
final class CollectBehaviourJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Отказы выше этого на заметной посещаемости — повод посмотреть страницу. */
    private const BOUNCE_ALERT = 70.0;

    /** Меньше стольких визитов — статистики нет, судить не о чем. */
    private const MIN_VISITS = 30;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(SiteAuditRepositoryInterface $audits): void
    {
        $audit = $audits->findById($this->auditId);
        $audit->loadMissing('project.organization');

        $metrika = new MetrikaClient(
            (string) ($audit->project->organization->yandex_token ?? ''),
            (int) ($audit->project->metrika_counter_id ?? 0),
        );

        if (! $metrika->available()) {
            return;
        }
        $summary = $metrika->summary();

        if ($summary === null) {
            return;
        }

        $landings = $metrika->landingPages() ?? [];
        $findings = array_values(array_filter(
            $audit->findings ?? [],
            static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'behaviour.'),
        ));

        // Проблемные страницы: высокие отказы там, где есть на чём судить.
        $problem = array_values(array_filter(
            $landings,
            static fn (array $row): bool => $row['visits'] >= self::MIN_VISITS && $row['bounce_rate'] >= self::BOUNCE_ALERT,
        ));

        usort($problem, static fn (array $a, array $b): int => $b['visits'] <=> $a['visits']);

        if ($problem !== []) {
            $findings[] = [
                'check' => 'behaviour.bounce',
                'code' => 'behaviour.bounce',
                'category' => 'content',
                'severity' => 'warning',
                'message' => 'Страницы входа с высокой долей отказов',
                'value' => array_slice($problem, 0, 15),
                'expected' => 'до '.self::BOUNCE_ALERT.'%',
            ];
        }

        $audits->update($audit, [
            'findings' => $findings,
            'metrics' => [...($audit->metrics ?? []), 'behaviour' => [
                'summary' => $summary,
                'timing' => $metrika->timing(),
                'landing_pages' => count($landings),
            ]],
        ]);

        $this->attachToPages($audit->id, $landings);
    }

    /**
     * Раскладываем поведение по страницам прогона: аудит и аналитика начинают
     * говорить об одной и той же странице.
     *
     * @param  array<int, array{url: string, visits: float, bounce_rate: float, page_depth: float}>  $landings
     */
    private function attachToPages(int $auditId, array $landings): void
    {
        if ($landings === []) {
            return;
        }

        $byUrl = [];

        foreach ($landings as $row) {
            $byUrl[$this->normalize($row['url'])] = $row;
        }

        PageAuditResult::where('site_audit_id', $auditId)
            ->cursor()
            ->each(function (PageAuditResult $result) use ($byUrl): void {
                $row = $byUrl[$this->normalize($result->url)] ?? null;

                if ($row === null) {
                    return;
                }

                $result->update(['metrics' => [...($result->metrics ?? []), 'behaviour' => [
                    'visits' => $row['visits'],
                    'bounce_rate' => $row['bounce_rate'],
                    'page_depth' => $row['page_depth'],
                ]]]);
            });
    }

    /** Метрика отдаёт адреса со своими хвостами — сравниваем по хосту и пути. */
    private function normalize(string $url): string
    {
        $parts = parse_url($url);
        $host = preg_replace('/^www\./i', '', mb_strtolower($parts['host'] ?? ''));

        return $host.(rtrim($parts['path'] ?? '/', '/') ?: '/');
    }
}
