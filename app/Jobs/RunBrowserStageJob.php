<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\Audit\BrowserAudit;
use App\Services\Audit\CruxClient;
use App\Services\Audit\HtmlValidator;
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

    /** Origin прогона: берём из первого результата, домен там уже нормализован. */
    private function origin(SiteAudit $audit): ?string
    {
        $url = PageAuditResult::where('site_audit_id', $audit->id)->value('url');

        if (! is_string($url)) {
            return null;
        }

        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) ? "{$parts['scheme']}://{$parts['host']}" : null;
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        BrowserAudit $browser,
        HtmlValidator $validator,
        CruxClient $crux,
    ): void {
        $audit = $audits->findById($this->auditId);

        // Полевые данные по домену — один запрос на прогон, не на страницу.
        if ($crux->enabled() && $this->origin($audit) !== null) {
            CollectFieldDataJob::dispatch($audit->id, (string) $this->origin($audit));
        }

        if ($audit->project->metrika_counter_id !== null) {
            CollectBehaviourJob::dispatch($audit->id);
        }

        if (! $browser->enabled() && ! $validator->enabled()) {
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

        $jobs = [];

        foreach ($targets as $target) {
            if ($browser->enabled()) {
                foreach ((array) config('audit.browser.viewports', ['mobile']) as $viewport) {
                    $jobs[] = new BrowserAuditJob($target->id, $target->url, trim((string) $viewport));
                }
            }

            if ($validator->enabled()) {
                $jobs[] = new ValidateHtmlJob($target->id, $target->url);
            }
        }

        if ($jobs === []) {
            FinalizeSiteAuditJob::dispatch($audit->id);

            return;
        }

        $batch = Bus::batch($jobs)
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
