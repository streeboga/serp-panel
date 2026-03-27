<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\KeywordRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Contracts\Repositories\SerpResultRepositoryInterface;
use App\Contracts\Repositories\SerpSnapshotRepositoryInterface;
use App\Services\Scrapers\Adapters\WebhookAdapter;

final readonly class WebhookService
{
    public function __construct(
        private ScraperRepositoryInterface $scraperRepository,
        private KeywordRepositoryInterface $keywordRepository,
        private SerpSnapshotRepositoryInterface $snapshotRepository,
        private SerpResultRepositoryInterface $resultRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function receiveSerp(array $data): array
    {
        // 1. Find scraper, verify secret
        $scraper = $this->scraperRepository->findById((int) $data['scraper_id']);
        $expectedSecret = $scraper->credentials['webhook_secret'] ?? '';
        if ($data['secret'] !== $expectedSecret) {
            abort(403, 'Invalid secret');
        }

        // 2. Find keyword
        $keyword = $this->keywordRepository->findById((int) $data['keyword_id']);
        $collectedAt = now()->toDateString();

        // 3. Create snapshot via repo
        $snapshot = $this->snapshotRepository->create([
            'keyword_id' => $keyword->id,
            'collected_at' => $collectedAt,
            'search_engine' => $data['engine'],
            'device' => $keyword->device,
            'region_id' => $data['region_id'],
            'total_results' => count($data['results']),
        ]);

        // 4. Parse and insert results via repo
        $items = WebhookAdapter::parseWebhookPayload($data);
        $sanitize = fn (?string $s): ?string => $s !== null ? mb_convert_encoding($s, 'UTF-8', 'UTF-8') : null;

        $rows = array_map(fn ($item) => [
            'snapshot_id' => $snapshot->id,
            'collected_at' => $collectedAt,
            'position' => $item->position,
            'url' => $sanitize($item->url),
            'domain' => $sanitize($item->domain),
            'title' => $sanitize($item->title),
            'description' => $sanitize($item->description),
            'snippet_type' => $item->snippetType,
            'is_ads' => $item->isAds,
        ], $items);

        $this->resultRepository->insertBatch($rows);

        return ['snapshot_id' => $snapshot->id, 'results_count' => count($items)];
    }
}
