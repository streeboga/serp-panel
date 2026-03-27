<?php

declare(strict_types=1);

namespace App\Jobs;

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
use Illuminate\Support\Facades\Log;

final class IndexDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine = 'google',
        public readonly int $limit = 100,
    ) {
        $this->onQueue('serp-scrape');
    }

    public function handle(ScraperFactory $scraperFactory): void
    {
        $domain = Domain::with('project')->findOrFail($this->domainId);
        $organizationId = $domain->project->organization_id;

        /** @var Scraper|null $scraper */
        $scraper = Scraper::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->first(fn (Scraper $s) => in_array($this->engine, $s->supported_engines ?? [], true));

        if (! $scraper) {
            Log::warning("IndexDomainJob: no active scraper for engine [{$this->engine}] in organization [{$organizationId}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $this->engine,
            device: 'desktop',
            regionId: 0,
            limit: $this->limit,
        );

        $response = $adapter->scrape($request);

        $today = now()->toDateString();

        // Delete old results for this engine + date to avoid duplicates
        $domain->indexResults()
            ->where('engine', $this->engine)
            ->where('collected_at', $today)
            ->delete();

        foreach ($response->results as $item) {
            DomainIndexResult::create([
                'domain_id' => $domain->id,
                'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
                'title' => $item->title ? mb_convert_encoding($item->title, 'UTF-8', 'UTF-8') : null,
                'description' => $item->description ? mb_convert_encoding($item->description, 'UTF-8', 'UTF-8') : null,
                'snippet_links' => null,
                'position' => $item->position,
                'engine' => $this->engine,
                'collected_at' => $today,
            ]);
        }

        $domain->update([
            'indexed_pages_count' => $domain->indexResults()
                ->where('engine', $this->engine)
                ->where('collected_at', $today)
                ->count(),
        ]);

        Log::info("IndexDomainJob: indexed {$domain->name}, found " . count($response->results) . " results");
    }
}
