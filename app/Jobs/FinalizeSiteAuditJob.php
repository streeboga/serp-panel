<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditStatus;
use App\Support\MutedCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Сводит оценку прогона: находки уровня сайта плюс усреднённые по страницам.
 */
final class FinalizeSiteAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        PageAuditResultRepositoryInterface $results,
        AuditResourceRepositoryInterface $resources,
    ): void {
        $audit = $audits->findById($this->auditId);
        $aggregate = $results->aggregate($audit->id);

        $batch = $audit->batch_id === null ? null : Bus::findBatch($audit->batch_id);

        $status = match (true) {
            $batch?->cancelled() === true => AuditStatus::Cancelled,
            default => AuditStatus::Completed,
        };

        // Считаем от находок, а не от накопленных счётчиков: джоб может быть
        // перезапущен, и складывать одно и то же второй раз нельзя.
        // Находки уровня сайта с первого этапа плюс те, что видны только теперь:
        // битые ссылки, тяжёлые картинки и дубли между страницами. Свои прошлые
        // отбрасываем — джоб может быть перезапущен, дублировать их нельзя.
        $siteFindings = array_values(array_filter(
            $audit->findings ?? [],
            static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'site.resources')
                && ! str_starts_with((string) ($f['check'] ?? ''), 'site.duplicate'),
        ));

        $siteFindings = [
            ...$siteFindings,
            ...$this->resourceFindings($resources, $audit->id),
            ...$this->duplicateFindings($results, $audit->id),
        ];

        // Политика заглушения — та, что записана в прогоне при запуске. Находки
        // остаются в списке с пометкой, но из счётчиков и оценки уходят: иначе
        // прогон показывает полторы тысячи находок там, где разбирать надо два
        // десятка, и число перестают читать.
        $mutedPolicy = MutedCodes::normalize($audit->muted_codes);
        $marked = MutedCodes::apply($siteFindings, $mutedPolicy);
        $siteFindings = $marked['findings'];

        $site = $this->countBySeverity($siteFindings);

        // Оценка сайта — находки уровня сайта и средняя по страницам в равных долях.
        $siteScore = max(0, 100 - $site['critical'] * 10 - $site['warning'] * 3 - $site['notice']);

        $score = $aggregate['score'] === null
            ? $siteScore
            : (int) round(($siteScore + $aggregate['score']) / 2);

        // Молчаливая потеря страниц — худший исход: прогон выглядит завершённым и
        // зелёным, хотя обошли четверть сайта. Говорим об этом вслух.
        $dropped = max(0, $audit->pages_total - $aggregate['pages']);

        $audits->update($audit, [
            'findings' => $siteFindings,
            'metrics' => [...($audit->metrics ?? []), 'resources' => $resources->summary($audit->id)],
            'status' => $status,
            'error' => $dropped > 0
                ? "Не удалось проверить {$dropped} из {$audit->pages_total} страниц — смотрите failed_jobs."
                : null,
            'score' => $score,
            'pages_done' => $aggregate['pages'],
            'issues_critical' => $site['critical'] + $aggregate['critical'],
            'issues_warning' => $site['warning'] + $aggregate['warning'],
            'issues_notice' => $site['notice'] + $aggregate['notice'],
            'issues_muted' => $marked['muted'] + $aggregate['muted'],
            'batch_id' => null,
            'finished_at' => now(),
        ]);

        Log::info("AuditSiteJob batch complete: audit {$audit->id} — {$aggregate['pages']} страниц, оценка {$score}");
    }

    /**
     * Битые ссылки и тяжёлые картинки — видны только после второго этапа.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resourceFindings(AuditResourceRepositoryInterface $resources, int $auditId): array
    {
        $summary = $resources->summary($auditId);
        $findings = [];

        if ($summary['broken'] > 0) {
            $findings[] = [
                'check' => 'site.resources.broken',
                'code' => 'site.resources.broken',
                'category' => 'technical',
                'severity' => 'critical',
                'message' => 'Внутренние ссылки и файлы, которые не открываются',
                'value' => $resources->broken($auditId)->take(20)->map(fn ($r): array => [
                    'url' => $r->url,
                    'status' => $r->status,
                    'refs' => $r->reference_count,
                ])->all(),
                'expected' => 0,
            ];
        }

        if ($summary['heaviest'] !== []) {
            $findings[] = [
                'check' => 'site.resources.heavy_images',
                'code' => 'site.resources.heavy_images',
                'category' => 'images',
                'severity' => 'warning',
                'message' => 'Тяжёлые изображения — их стоит пережать',
                'value' => array_map(static fn (array $i): array => [
                    'url' => $i['url'],
                    'kb' => (int) round($i['bytes'] / 1024),
                ], $summary['heaviest']),
                'expected' => 'до 300 KB',
            ];
        }

        return $findings;
    }

    /**
     * Дубли title и description между страницами: постраничный аудитор их
     * не видит принципиально.
     *
     * @return array<int, array<string, mixed>>
     */
    private function duplicateFindings(PageAuditResultRepositoryInterface $results, int $auditId): array
    {
        $findings = [];

        foreach (['title', 'description'] as $metric) {
            $duplicates = $results->duplicatesByMetric($auditId, $metric);

            if ($duplicates === []) {
                continue;
            }

            $findings[] = [
                'check' => "site.duplicate.{$metric}",
                'code' => "site.duplicate.{$metric}",
                'category' => 'meta',
                'severity' => 'warning',
                'message' => "Одинаковый {$metric} на разных страницах",
                'value' => $duplicates,
                'expected' => 'уникальный на каждой странице',
            ];
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{critical: int, warning: int, notice: int}
     */
    private function countBySeverity(array $findings): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'notice' => 0];

        foreach ($findings as $finding) {
            if (MutedCodes::isMuted($finding)) {
                continue;
            }

            $severity = $finding['severity'] ?? null;

            if (is_string($severity) && isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }
}
