<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;

final class XmlRiverAdapter implements SerpScraperAdapter
{
    /** @param array<string, mixed> $credentials */
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
            'num' => $request->limit,
        ];

        if ($request->engine === 'yandex') {
            $params['lr'] = $request->yandexLr;
        } else {
            $params['gl'] = $request->googleGl;
            $params['hl'] = $request->googleHl;
        }

        // XMLRiver returns XML at /search/xml
        $url = rtrim($this->baseUrl, '/');
        if (!str_contains($url, '/search')) {
            $url .= '/search/xml';
        }

        $response = Http::timeout(60)->get($url, $params);
        $body = $response->body();

        $results = $this->parseXmlResponse($body);

        return new ScrapeResponse(
            results: $results,
            totalResults: count($results),
            rawResponse: $body,
        );
    }

    /** @return SerpResultItem[] */
    private function parseXmlResponse(string $xml): array
    {
        $results = [];

        try {
            $doc = new \SimpleXMLElement($xml);

            // Check for error
            if (isset($doc->response->error)) {
                return [];
            }

            $position = 0;
            $groups = $doc->response->results->grouping->group ?? [];

            foreach ($groups as $group) {
                $position++;
                $doc2 = $group->doc;
                if (!$doc2) {
                    continue;
                }

                $url = (string) ($doc2->url ?? '');
                $host = parse_url($url, PHP_URL_HOST) ?: '';

                $results[] = new SerpResultItem(
                    position: $position,
                    url: $url,
                    domain: $host,
                    title: (string) ($doc2->title ?? ''),
                    description: (string) ($doc2->passages->passage ?? ''),
                    snippetType: (string) ($doc2->contenttype ?? 'organic'),
                    isAds: false,
                );
            }
        } catch (\Exception) {
            // XML parsing failed — return empty
        }

        return $results;
    }

    public function supportedEngines(): array
    {
        return ['google', 'yandex'];
    }

    public function healthCheck(): bool
    {
        try {
            $url = rtrim($this->baseUrl, '/');
            if (!str_contains($url, '/search')) {
                $url .= '/search/xml';
            }

            $response = Http::timeout(10)->get($url, [
                'user' => $this->credentials['user'] ?? '',
                'key' => $this->credentials['key'] ?? '',
                'query' => 'test',
                'num' => 1,
            ]);

            return str_contains($response->body(), '<yandexsearch');
        } catch (\Exception) {
            return false;
        }
    }
}
