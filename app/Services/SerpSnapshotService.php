<?php

namespace App\Services;

use App\Models\Region;
use App\Models\ScrapeJob;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;

class SerpSnapshotService
{
    public function __construct(
        private readonly ScraperFactory $scraperFactory,
    ) {}

    public function scrape(ScrapeJob $job): void
    {
        $keyword = $job->keyword;
        $scraper = $job->scraper;
        $region = Region::find($job->region_id);

        $adapter = $this->scraperFactory->make($scraper);

        $request = new ScrapeRequest(
            keyword: $keyword->keyword,
            engine: $job->engine->value,
            device: $job->device->value,
            regionId: $job->region_id,
            yandexLr: $region?->yandex_lr,
            googleGl: $region?->google_gl,
            googleHl: $region?->google_hl,
        );

        $response = $adapter->scrape($request);

        $collectedAt = now()->toDateString();

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'collected_at' => $collectedAt,
            'search_engine' => $job->engine->value,
            'device' => $job->device->value,
            'region_id' => $job->region_id,
            'total_results' => $response->totalResults,
        ]);

        $rows = array_map(fn ($item) => [
            'snapshot_id' => $snapshot->id,
            'collected_at' => $collectedAt,
            'position' => $item->position,
            'url' => $item->url,
            'domain' => $item->domain,
            'title' => $item->title,
            'description' => $item->description,
            'snippet_type' => $item->snippetType,
            'is_ads' => $item->isAds,
        ], $response->results);

        foreach (array_chunk($rows, 50) as $chunk) {
            SerpResult::insert($chunk);
        }

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'raw_response' => $response->rawResponse,
        ]);
    }
}
