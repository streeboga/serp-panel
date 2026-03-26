<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\SerpResultRepositoryInterface;
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

    public function handle(ClassificationService $service, SerpResultRepositoryInterface $resultRepository): void
    {
        $results = $resultRepository->getBySnapshotIdAndDate($this->snapshotId, $this->collectedAt);

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
