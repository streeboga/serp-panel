<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Services\Audit\BrowserAudit;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Оценка Lighthouse по нескольким показательным страницам прогона.
 *
 * Кладётся в метрики прогона, а не страницы: это витринная цифра для отчёта,
 * а подробные замеры по каждой странице у нас и так свои.
 */
final class LighthouseJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $timeout = 300;

    public function __construct(
        public readonly int $auditId,
        public readonly string $url,
        public readonly string $viewport = 'mobile',
    ) {
        $this->onQueue('audit-browser');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(SiteAuditRepositoryInterface $audits, BrowserAudit $browser): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $report = $browser->lighthouse($this->url, $this->viewport);

        // Не ответил — страница осталась без оценки, а не с нулевой.
        if ($report === null) {
            return;
        }

        $audit = $audits->findById($this->auditId);
        $existing = $audit->metrics['lighthouse'] ?? [];
        $existing[] = [...$report, 'url' => $this->url];

        $audits->update($audit, [
            'metrics' => [...($audit->metrics ?? []), 'lighthouse' => $existing],
        ]);
    }
}
