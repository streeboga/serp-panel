<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Models\PageAuditResult;
use App\Services\Audit\BrowserAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Третий этап: браузерные замеры.
 *
 * Гоняется по выборке, а не по всему сайту: полминуты на страницу означает, что
 * 234 страницы это два часа работы Chromium. Сначала берём страницы проекта —
 * те, у которых есть целевые ключи и за которые кто-то отвечает.
 */
final class RunBrowserStageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(SiteAuditRepositoryInterface $audits, BrowserAudit $browser): void
    {
        $audit = $audits->findById($this->auditId);

        if (! $browser->enabled()) {
            FinalizeSiteAuditJob::dispatch($audit->id);

            return;
        }

        $limit = (int) config('audit.browser.max_pages');

        $targets = PageAuditResult::query()
            ->where('site_audit_id', $audit->id)
            ->where('http_status', 200)
            // Сначала страницы проекта, потом самые проблемные по оценке.
            ->orderByRaw('page_id is null')
            ->orderBy('score')
            ->limit($limit)
            ->get(['id', 'url']);

        if ($targets->isEmpty()) {
            FinalizeSiteAuditJob::dispatch($audit->id);

            return;
        }

        $auditId = $audit->id;

        $batch = Bus::batch($targets->map(fn (PageAuditResult $r): BrowserAuditJob => new BrowserAuditJob($r->id, $r->url))->all())
            ->name("audit-browser:{$auditId}")
            ->onQueue('audit-browser')
            ->allowFailures()
            ->finally(fn () => FinalizeSiteAuditJob::dispatch($auditId))
            ->dispatch();

        $audits->update($audit, ['batch_id' => $batch->id]);

        $total = PageAuditResult::where('site_audit_id', $auditId)->where('http_status', 200)->count();

        if ($total > $limit) {
            Log::info("RunBrowserStageJob: audit {$auditId} — браузером смотрим {$limit} страниц из {$total}");
        }
    }
}
