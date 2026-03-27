<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class XmlRiverAdapter implements SerpScraperAdapter
{
    private const RESULTS_PER_PAGE = 10;

    /** @param array<string, mixed> $credentials */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $url = rtrim($this->baseUrl, '/');
        if (! str_contains($url, '/search')) {
            $url .= '/search/xml';
        }

        $firstPage = $request->engine === 'yandex' ? 0 : 1;

        $baseParams = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'groupby' => self::RESULTS_PER_PAGE,
        ];

        if ($request->engine === 'yandex') {
            $baseParams['lr'] = $request->yandexLr;
        } else {
            $baseParams['gl'] = $request->googleGl;
            $baseParams['hl'] = $request->googleHl;
        }

        $allResults = [];
        $page = $firstPage;
        $maxResults = $request->limit;

        while (count($allResults) < $maxResults) {
            try {
                $response = Http::timeout(60)->get($url, array_merge($baseParams, ['page' => $page]));
                $pageResults = $this->parseXmlResponse($response->body(), count($allResults));

                if (empty($pageResults)) {
                    break;
                }

                $allResults = array_merge($allResults, $pageResults);

                if (count($pageResults) < self::RESULTS_PER_PAGE) {
                    break;
                }

                $page++;
            } catch (\Exception $e) {
                Log::warning('XMLRiver page fetch failed', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        return new ScrapeResponse(
            results: $allResults,
            totalResults: count($allResults),
            rawResponse: '',
        );
    }

    /** @return SerpResultItem[] */
    private function parseXmlResponse(string $xml, int $positionOffset = 0): array
    {
        $results = [];

        try {
            $doc = new \SimpleXMLElement($xml);

            if (isset($doc->response->error)) {
                return [];
            }

            $position = $positionOffset;
            $groups = $doc->response->results->grouping->group ?? [];

            foreach ($groups as $group) {
                $position++;
                $doc2 = $group->doc;
                if (! $doc2) {
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
            if (! str_contains($url, '/search')) {
                $url .= '/search/xml';
            }

            $response = Http::timeout(10)->get($url, [
                'user' => $this->credentials['user'] ?? '',
                'key' => $this->credentials['key'] ?? '',
                'query' => 'test',
                'groupby' => 1,
                'page' => 1,
            ]);

            return str_contains($response->body(), '<yandexsearch');
        } catch (\Exception) {
            return false;
        }
    }
}
