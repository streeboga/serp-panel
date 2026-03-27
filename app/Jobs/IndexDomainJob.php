<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Device;
use App\Enums\Engine;
use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Scraper;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

final class IndexDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine = 'google',
        public readonly int $limit = 100,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(ScraperFactory $scraperFactory): void
    {
        // Guard: don't run if another batch is already active
        $domain = Domain::with('project')->findOrFail($this->domainId);

        if ($domain->index_batch_id) {
            $batch = Bus::findBatch($domain->index_batch_id);
            if ($batch && ! $batch->finished()) {
                Log::info("IndexDomainJob: skipping {$domain->name}, batch already running");
                $this->release(60);

                return;
            }
        }

        $this->process($domain, $scraperFactory);
    }

    private function process(Domain $domain, ScraperFactory $scraperFactory): void
    {
        $organizationId = $domain->project->organization_id;

        /** @var Scraper|null $scraper */
        $scraper = Scraper::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->first(fn (Scraper $s) => in_array($this->engine, $s->supported_engines ?? [], true));

        if (! $scraper) {
            Log::warning("IndexDomainJob: no active scraper for engine [{$this->engine}] in org [{$organizationId}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);
        $engineEnum = Engine::from($this->engine);
        $firstPage = $engineEnum === Engine::Yandex ? 0 : 1;

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $engineEnum->value,
            device: Device::Desktop->value,
            regionId: 0,
            limit: $this->limit,
        );

        // Fetch first page — let exceptions propagate for retry
        $firstResponse = $adapter->scrapePage($request, $firstPage);
        $totalFound = $firstResponse->totalResults;
        $today = now()->toDateString();

        // Upsert first page results
        DomainIndexResult::upsertFromScrape($domain->id, $this->engine, $today, $firstResponse->results);

        if ($totalFound === 0 || empty($firstResponse->results)) {
            $domain->update(['indexed_pages_count' => 0, 'index_batch_id' => null]);
            Log::info("IndexDomainJob: {$domain->name} — not in index");

            return;
        }

        // Calculate remaining pages
        $maxPages = min(
            (int) ceil($totalFound / 10),
            (int) ceil($this->limit / 10),
            100, // Google hard limit ~1000 results
        );

        if ($maxPages <= 1) {
            $count = $domain->indexResults()->where('engine', $this->engine)->count();
            $domain->update(['indexed_pages_count' => $count, 'index_batch_id' => null]);
            Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, single page");

            return;
        }

        // Dispatch batch for remaining pages
        $domainId = $domain->id;
        $engine = $this->engine;
        $jobs = [];

        for ($page = $firstPage + 1; $page < $firstPage + $maxPages; $page++) {
            $jobs[] = new FetchIndexPageJob(
                domainId: $domainId,
                engine: $engine,
                page: $page,
                collectedAt: $today,
            );
        }

        $batch = Bus::batch($jobs)
            ->name("index:{$domain->name}:{$engine}:{$today}")
            ->onQueue('indexing')
            ->allowFailures()
            ->finally(function () use ($domainId, $engine) {
                $domain = Domain::find($domainId);
                if (! $domain) {
                    return;
                }

                $count = $domain->indexResults()->where('engine', $engine)->count();

                $domain->update([
                    'indexed_pages_count' => $count,
                    'index_batch_id' => null,
                ]);

                Log::info("IndexDomainJob batch complete: {$domain->name} — {$count} pages collected");
            })
            ->dispatch();

        $domain->update(['index_batch_id' => $batch->id]);

        if ($totalFound > 1000) {
            Log::warning("IndexDomainJob: {$domain->name} has {$totalFound} indexed pages, truncated to max 1000");
        }

        Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, dispatched " . count($jobs) . ' page jobs');
    }

}
