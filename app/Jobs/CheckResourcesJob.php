<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Второй этап прогона: обход ссылок и картинок, собранных со страниц.
 *
 * Отдельным этапом, а не внутри страничной джобы, ровно по одной причине: одна
 * и та же ссылка встречается на сотне страниц, а запросить её нужно один раз.
 */
final class CheckResourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $auditId,
    ) {
        $this->onQueue('audit');
    }

    public function handle(
        SiteAuditRepositoryInterface $audits,
        AuditResourceRepositoryInterface $resources,
    ): void {
        $audit = $audits->findById($this->auditId);

        if (! (bool) config('audit.check_resources')) {
            RunBrowserStageJob::dispatch($audit->id);

            return;
        }

        $limit = (int) config('audit.max_resources');
        $pending = $resources->pending($audit->id, $limit);

        if ($pending->isEmpty()) {
            RunBrowserStageJob::dispatch($audit->id);

            return;
        }

        $jobs = $pending
            ->map(fn ($resource): CheckResourceJob => new CheckResourceJob($resource->id, $resource->url))
            ->all();

        $auditId = $audit->id;

        $batch = Bus::batch($jobs)
            ->name("audit-resources:{$auditId}")
            ->onQueue('audit-assets')
            ->allowFailures()
            ->finally(fn () => RunBrowserStageJob::dispatch($auditId))
            ->dispatch();

        $audits->update($audit, ['batch_id' => $batch->id]);

        // Молчаливый потолок читается как «всё проверено» — говорим вслух.
        $total = $resources->pending($audit->id, PHP_INT_MAX)->count();

        if ($total > $limit) {
            Log::warning("CheckResourcesJob: audit {$auditId} — ресурсов {$total}, проверяем первые {$limit}");
        }

        Log::info("CheckResourcesJob: audit {$auditId} — в очереди ".count($jobs).' ресурсов');
    }
}
