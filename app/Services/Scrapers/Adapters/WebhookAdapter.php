<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;

/**
 * Webhook/API adapter — external service pushes SERP data to our API.
 * Scrape method returns empty (data comes via incoming webhook).
 * Used when external tools collect SERP and POST results to our endpoint.
 */
final class WebhookAdapter implements SerpScraperAdapter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        // Webhook adapter doesn't actively scrape — it receives data via API
        // Return empty response, actual data comes through webhook endpoint
        return new ScrapeResponse(
            results: [],
            totalResults: 0,
            rawResponse: json_encode(['mode' => 'webhook', 'message' => 'Data should be pushed via POST /api/v1/webhooks/serp']),
        );
    }

    public function supportedEngines(): array
    {
        return ['google', 'yandex'];
    }

    public function healthCheck(): bool
    {
        return true; // Always healthy — it's passive
    }

    /**
     * Parse incoming webhook payload into SerpResultItems.
     * External service should POST JSON with format:
     * {
     *   "keyword": "...",
     *   "engine": "yandex",
     *   "region_id": 2,
     *   "results": [
     *     {"position": 1, "url": "...", "domain": "...", "title": "..."},
     *     ...
     *   ]
     * }
     *
     * @return SerpResultItem[]
     */
    public static function parseWebhookPayload(array $data): array
    {
        $results = [];
        foreach ($data['results'] ?? [] as $item) {
            $url = $item['url'] ?? '';
            $results[] = new SerpResultItem(
                position: (int) ($item['position'] ?? 0),
                url: $url,
                domain: $item['domain'] ?? parse_url($url, PHP_URL_HOST) ?: '',
                title: $item['title'] ?? '',
                description: $item['description'] ?? null,
                snippetType: $item['snippet_type'] ?? 'organic',
                isAds: (bool) ($item['is_ads'] ?? false),
            );
        }

        return $results;
    }
}
