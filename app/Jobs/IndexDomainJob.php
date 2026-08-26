<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Enums\Device;
use App\Enums\Engine;
use App\Models\Domain;
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
    ) {
        $this->onQueue('indexing');
    }

    public function handle(
        DomainRepositoryInterface $domainRepository,
        ScraperRepositoryInterface $scraperRepository,
        DomainIndexResultRepositoryInterface $indexResultRepository,
        ScraperFactory $scraperFactory,
    ): void {
        $domain = $domainRepository->findWithProject($this->domainId);

        if ($domain->index_batch_id) {
            $batch = Bus::findBatch($domain->index_batch_id);
            if ($batch && ! $batch->finished()) {
                Log::info("IndexDomainJob: skipping {$domain->name}, batch already running");
                $this->release(60);

                return;
            }
        }

        $this->process($domain, $domainRepository, $scraperRepository, $indexResultRepository, $scraperFactory);
    }

    private function process(
        Domain $domain,
        DomainRepositoryInterface $domainRepository,
        ScraperRepositoryInterface $scraperRepository,
        DomainIndexResultRepositoryInterface $indexResultRepository,
        ScraperFactory $scraperFactory,
    ): void {
        $organizationId = $domain->project->organization_id;

        $scraper = $scraperRepository->findActiveForEngine($organizationId, $this->engine);

        if (! $scraper) {
            Log::warning("IndexDomainJob: no active scraper for engine [{$this->engine}] in org [{$organizationId}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);

        if (! $adapter->supportsPageScraping()) {
            Log::warning("IndexDomainJob: adapter [{$scraper->type}] does not support page scraping");

            return;
        }

        $engineEnum = Engine::from($this->engine);
        $firstPage = $engineEnum === Engine::Yandex ? 0 : 1;

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $engineEnum->value,
            device: Device::Desktop->value,
            regionId: 0,
        );

        $firstResponse = $adapter->scrapePage($request, $firstPage);
        $totalFound = $firstResponse->totalResults;
        $today = now()->toDateString();

        $indexResultRepository->upsertFromScrape($domain->id, $this->engine, $today, $firstResponse->results);

        if ($totalFound === 0 || empty($firstResponse->results)) {
            $domainRepository->update($domain, ['indexed_pages_count' => 0, 'index_batch_id' => null]);
            Log::info("IndexDomainJob: {$domain->name} — not in index");

            return;
        }

        // Fetch all pages reported by search engine, safety cap at 100 pages (1000 URLs).
        // If totalFound is suspiciously low but first page was full, use a generous fallback.
        $resultsPerPage = 10;
        $effectiveTotal = $totalFound;

        if ($effectiveTotal <= $resultsPerPage && count($firstResponse->results) >= $resultsPerPage) {
            $effectiveTotal = 1000;
        }

        $maxPages = min(
            (int) ceil($effectiveTotal / $resultsPerPage),
            100,
        );

        if ($maxPages <= 1) {
            $count = $indexResultRepository->countForDomain($domain->id, $this->engine);
            $domainRepository->update($domain, ['indexed_pages_count' => $count, 'index_batch_id' => null]);
            Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, single page");

            return;
        }

        $this->dispatchBatch($domain, $domainRepository, $firstPage, $maxPages, $today, $totalFound);
    }

    private function dispatchBatch(
        Domain $domain,
        DomainRepositoryInterface $domainRepository,
        int $firstPage,
        int $maxPages,
        string $today,
        int $totalFound,
    ): void {
        $jobs = [];

        for ($page = $firstPage + 1; $page < $firstPage + $maxPages; $page++) {
            $jobs[] = new FetchIndexPageJob(
                domainId: $domain->id,
                engine: $this->engine,
                page: $page,
                collectedAt: $today,
            );
        }

        $batch = Bus::batch($jobs)
            ->name("index:{$domain->name}:{$this->engine}:{$today}")
            ->onQueue('indexing')
            ->allowFailures()
            ->finally(fn () => FinalizeIndexBatchJob::dispatch($domain->id, $this->engine))
            ->dispatch();

        $domainRepository->update($domain, ['index_batch_id' => $batch->id]);

        if ($totalFound > 1000) {
            Log::warning("IndexDomainJob: {$domain->name} has {$totalFound} indexed pages, truncated to max 1000");
        }

        Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, dispatched " . count($jobs) . ' page jobs');
    }
}
