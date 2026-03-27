<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Enums\Device;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class FetchIndexPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    public int $timeout = 90;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine,
        public readonly int $page,
        public readonly string $collectedAt,
    ) {
        $this->onQueue('indexing');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('xmlriver')];
    }

    public function handle(
        DomainRepositoryInterface $domainRepository,
        ScraperRepositoryInterface $scraperRepository,
        DomainIndexResultRepositoryInterface $indexResultRepository,
        ScraperFactory $scraperFactory,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $domain = $domainRepository->findWithProject($this->domainId);
        $organizationId = $domain->project->organization_id;

        $scraper = $scraperRepository->findActiveForEngine($organizationId, $this->engine);

        if (! $scraper) {
            Log::warning("FetchIndexPageJob: no active scraper for engine [{$this->engine}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $this->engine,
            device: Device::Desktop->value,
            regionId: 0,
        );

        $response = $adapter->scrapePage($request, $this->page);

        $indexResultRepository->upsertFromScrape($domain->id, $this->engine, $this->collectedAt, $response->results);
    }
}
