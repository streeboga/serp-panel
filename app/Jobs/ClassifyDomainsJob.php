<?php

namespace App\Jobs;

use App\Models\SerpResult;
use App\Services\ClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $snapshotId,
        public readonly string $collectedAt,
        public readonly int $organizationId,
    ) {
        $this->onQueue('classification');
    }

    public function handle(ClassificationService $service): void
    {
        $results = SerpResult::where('snapshot_id', $this->snapshotId)
            ->where('collected_at', $this->collectedAt)
            ->get();

        foreach ($results as $result) {
            $service->classify(
                $result->domain,
                $this->organizationId,
                $result->url,
                $result->title,
            );
        }
    }
}
