<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class FinalizeIndexBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(
        DomainRepositoryInterface $domainRepository,
        DomainIndexResultRepositoryInterface $indexResultRepository,
    ): void {
        $domain = $domainRepository->findById($this->domainId);

        $count = $indexResultRepository->countForDomain($domain->id, $this->engine);

        $domainRepository->update($domain, [
            'indexed_pages_count' => $count,
            'index_batch_id' => null,
        ]);

        Log::info("IndexDomainJob batch complete: {$domain->name} — {$count} pages collected");
    }
}
