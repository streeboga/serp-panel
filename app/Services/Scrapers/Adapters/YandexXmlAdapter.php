<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;

/**
 * Direct Yandex XML API adapter.
 * Uses https://yandex.ru/search/xml endpoint with user/key credentials.
 */
final class YandexXmlAdapter implements SerpScraperAdapter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $url = $this->baseUrl ?: 'https://yandex.ru/search/xml';

        $params = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'lr' => $request->yandexLr ?? 213,
            'sortby' => 'rlv',
            'filter' => 'none',
            'groupby' => "attr=d.mode=deep.groups-on-page={$request->limit}.docs-in-group=1",
        ];

        $response = Http::timeout(60)->get($url, $params);
        $body = $response->body();

        return new ScrapeResponse(
            results: $this->parseXml($body),
            totalResults: 0,
            rawResponse: mb_convert_encoding($body, 'UTF-8', 'UTF-8'),
        );
    }

    /** @return SerpResultItem[] */
    private function parseXml(string $xml): array
    {
        $results = [];
        try {
            $doc = new \SimpleXMLElement($xml);
            if (isset($doc->response->error)) return [];

            $position = 0;
            foreach ($doc->response->results->grouping->group ?? [] as $group) {
                $position++;
                $d = $group->doc;
                if (!$d) continue;

                $url = (string) ($d->url ?? '');
                $results[] = new SerpResultItem(
                    position: $position,
                    url: $url,
                    domain: parse_url($url, PHP_URL_HOST) ?: '',
                    title: (string) ($d->title ?? ''),
                    description: (string) ($d->passages->passage ?? ''),
                    snippetType: 'organic',
                    isAds: false,
                );
            }
        } catch (\Exception) {}
        return $results;
    }

    public function supportedEngines(): array
    {
        return ['yandex'];
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl ?: 'https://yandex.ru/search/xml', [
                'user' => $this->credentials['user'] ?? '',
                'key' => $this->credentials['key'] ?? '',
                'query' => 'test',
                'groupby' => 'attr=d.mode=deep.groups-on-page=1.docs-in-group=1',
            ]);
            return str_contains($response->body(), '<yandexsearch');
        } catch (\Exception) {
            return false;
        }
    }
}
