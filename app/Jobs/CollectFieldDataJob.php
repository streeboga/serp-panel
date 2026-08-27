<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Services\Audit\CruxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SerpAudit\Category;
use SerpAudit\Finding;
use SerpAudit\Severity;

/**
 * Полевые показатели по домену: один запрос на прогон, кладётся в метрики прогона.
 */
final class CollectFieldDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Пороги Core Web Vitals: «хорошо» и «плохо» по методике Google. */
    private const THRESHOLDS = [
        'largest_contentful_paint' => [2500, 4000, 'LCP', 'мс'],
        'interaction_to_next_paint' => [200, 500, 'INP', 'мс'],
        'cumulative_layout_shift' => [0.1, 0.25, 'CLS', ''],
        'experimental_time_to_first_byte' => [800, 1800, 'TTFB', 'мс'],
    ];

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $auditId,
        public readonly string $origin,
    ) {
        $this->onQueue('audit');
    }

    public function handle(SiteAuditRepositoryInterface $audits, CruxClient $crux): void
    {
        $field = $crux->forUrl($this->origin);

        // Ключа нет или CrUX не набрал данных — это «данных нет», а не «всё хорошо».
        if ($field === null) {
            return;
        }

        $audit = $audits->findById($this->auditId);
        $findings = array_values(array_filter(
            $audit->findings ?? [],
            static fn (array $f): bool => ! str_starts_with((string) ($f['check'] ?? ''), 'field.'),
        ));

        foreach (self::THRESHOLDS as $metric => [$good, $poor, $label, $unit]) {
            $value = $field['metrics'][$metric]['p75'] ?? null;

            if (! is_numeric($value) || $value <= $good) {
                continue;
            }

            $findings[] = (new Finding(
                'field.'.$metric,
                'field.'.$metric,
                Category::TECHNICAL,
                $value >= $poor ? Severity::Critical : Severity::Warning,
                "{$label} у реальных пользователей хуже нормы"
                    .($field['scope'] === 'origin' ? ' (данные по домену: по этому URL их не набралось)' : ''),
                trim("{$value} {$unit}"),
                trim("до {$good} {$unit}"),
            ))->toArray();
        }

        $audits->update($audit, [
            'findings' => $findings,
            'metrics' => [...($audit->metrics ?? []), 'field' => $field],
        ]);
    }
}
