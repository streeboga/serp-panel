<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Enums\Engine;
use App\Enums\IndexingStatus;
use App\Jobs\IndexDomainJob;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;

final readonly class DomainIndexingService
{
    public function __construct(
        private DomainRepositoryInterface $domainRepository,
        private DomainIndexResultRepositoryInterface $indexResultRepository,
    ) {}

    public function startIndexing(Domain $domain, Engine $engine, int $limit = 100): void
    {
        IndexDomainJob::dispatch($domain->id, $engine->value, $limit);
    }

    /**
     * @return array{status: string, total_found: int|null, collected: int, progress: int, batch_id: string|null}
     */
    public function getStatus(Domain $domain, Engine $engine): array
    {
        if (! $domain->index_batch_id) {
            return [
                'status' => IndexingStatus::Idle->value,
                'total_found' => $domain->indexed_pages_count,
                'collected' => $domain->indexed_pages_count ?? 0,
                'progress' => 100,
                'batch_id' => null,
            ];
        }

        $batch = Bus::findBatch($domain->index_batch_id);

        if (! $batch) {
            return [
                'status' => IndexingStatus::Idle->value,
                'total_found' => $domain->indexed_pages_count,
                'collected' => $domain->indexed_pages_count ?? 0,
                'progress' => 100,
                'batch_id' => null,
            ];
        }

        $status = match (true) {
            $batch->cancelled() => IndexingStatus::Cancelled,
            $batch->hasFailures() && $batch->finished() => IndexingStatus::Failed,
            $batch->finished() => IndexingStatus::Completed,
            default => IndexingStatus::Indexing,
        };

        return [
            'status' => $status->value,
            'total_found' => $domain->indexed_pages_count,
            'collected' => $this->indexResultRepository->countForDomainOnDate(
                $domain->id,
                $engine->value,
                now()->toDateString(),
            ),
            'progress' => $batch->progress(),
            'batch_id' => $batch->id,
        ];
    }

    public function cancel(Domain $domain): void
    {
        if (! $domain->index_batch_id) {
            return;
        }

        $batch = Bus::findBatch($domain->index_batch_id);
        $batch?->cancel();

        $this->domainRepository->update($domain, ['index_batch_id' => null]);
    }

    /**
     * @return Collection<int, \App\Models\DomainIndexResult>
     */
    public function getIndexResults(Domain $domain, string $engine, int $limit = 100): Collection
    {
        return $this->indexResultRepository->getForDomain($domain->id, $engine, $limit);
    }
}
