<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditStatus;
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
        $site = $this->countBySeverity($audit->findings ?? []);

        // Оценка сайта — находки уровня сайта и средняя по страницам в равных долях.
        $siteScore = max(0, 100 - $site['critical'] * 10 - $site['warning'] * 3 - $site['notice']);

        $score = $aggregate['score'] === null
            ? $siteScore
            : (int) round(($siteScore + $aggregate['score']) / 2);

        // Молчаливая потеря страниц — худший исход: прогон выглядит завершённым и
        // зелёным, хотя обошли четверть сайта. Говорим об этом вслух.
        $dropped = max(0, $audit->pages_total - $aggregate['pages']);

        $audits->update($audit, [
            'status' => $status,
            'error' => $dropped > 0
                ? "Не удалось проверить {$dropped} из {$audit->pages_total} страниц — смотрите failed_jobs."
                : null,
            'score' => $score,
            'pages_done' => $aggregate['pages'],
            'issues_critical' => $site['critical'] + $aggregate['critical'],
            'issues_warning' => $site['warning'] + $aggregate['warning'],
            'issues_notice' => $site['notice'] + $aggregate['notice'],
            'batch_id' => null,
            'finished_at' => now(),
        ]);

        Log::info("AuditSiteJob batch complete: audit {$audit->id} — {$aggregate['pages']} страниц, оценка {$score}");
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{critical: int, warning: int, notice: int}
     */
    private function countBySeverity(array $findings): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'notice' => 0];

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? null;

            if (is_string($severity) && isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }
}
