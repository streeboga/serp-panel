<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RegionRepositoryInterface;
use App\Contracts\Repositories\ScrapeJobRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Events\SerpSnapshotCollected;
use App\Models\ScrapeJob;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;

final readonly class SerpSnapshotService
{
    public function __construct(
        private ScraperFactory $scraperFactory,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
        private SerpResultRepositoryInterface $resultRepository,
        private RegionRepositoryInterface $regionRepository,
        private ScrapeJobRepositoryInterface $scrapeJobRepository,
    ) {}

    public function scrape(ScrapeJob $job): void
    {
        $keyword = $job->keyword;
        $scraper = $job->scraper;
        $region = $this->regionRepository->findById($job->region_id);

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

        $snapshot = $this->snapshotRepository->create([
            'keyword_id' => $keyword->id,
            'collected_at' => $collectedAt,
            'search_engine' => $job->engine->value,
            'device' => $job->device->value,
            'region_id' => $job->region_id,
            'total_results' => $response->totalResults,
        ]);

        $sanitize = fn (?string $s): ?string => $s !== null ? mb_convert_encoding($s, 'UTF-8', 'UTF-8') : null;

        $rows = array_map(fn ($item) => [
            'snapshot_id' => $snapshot->id,
            'collected_at' => $collectedAt,
            'position' => $item->position,
            'url' => $sanitize($item->url),
            'domain' => $sanitize($item->domain),
            'title' => $sanitize($item->title),
            'description' => $sanitize($item->description),
            'snippet_type' => $sanitize($item->snippetType),
            'is_ads' => $item->isAds,
        ], $response->results);

        $this->resultRepository->insertBatch($rows);

        $this->scrapeJobRepository->update($job, [
            'status' => 'completed',
            'completed_at' => now(),
            'raw_response' => mb_convert_encoding($response->rawResponse ?? '', 'UTF-8', 'UTF-8'),
        ]);

        $keyword->load('cluster.category.domain.project');
        $organizationId = $keyword->cluster->category->domain->project->organization_id;

        SerpSnapshotCollected::dispatch(
            $snapshot->id,
            $keyword->id,
            $organizationId,
            $collectedAt,
        );
    }
}
