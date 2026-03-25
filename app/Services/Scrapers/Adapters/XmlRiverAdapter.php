<?php

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;

class XmlRiverAdapter implements SerpScraperAdapter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $params = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'groupby' => $request->limit,
            'device' => $request->device,
        ];

        if ($request->engine === 'yandex') {
            $params['lr'] = $request->yandexLr;
            $params['engine'] = 'yandex';
        } else {
            $params['gl'] = $request->googleGl;
            $params['hl'] = $request->googleHl;
            $params['engine'] = 'google';
        }

        $response = Http::timeout(60)->get($this->baseUrl.'/search', $params);
        $body = $response->body();
        $data = json_decode($body, true) ?: [];

        $results = [];
        foreach (($data['results'] ?? []) as $i => $item) {
            $url = $item['url'] ?? '';
            $results[] = new SerpResultItem(
                position: $i + 1,
                url: $url,
                domain: parse_url($url, PHP_URL_HOST) ?: '',
                title: $item['title'] ?? null,
                description: $item['snippet'] ?? $item['description'] ?? null,
                snippetType: $item['type'] ?? 'organic',
                isAds: (bool) ($item['is_ad'] ?? false),
            );
        }

        return new ScrapeResponse(
            results: $results,
            totalResults: $data['total_results'] ?? count($results),
            rawResponse: $body,
        );
    }

    public function supportedEngines(): array
    {
        return ['google', 'yandex'];
    }

    public function healthCheck(): bool
    {
        try {
            return Http::timeout(10)->get($this->baseUrl.'/status')->ok();
        } catch (\Exception) {
            return false;
        }
    }
}
