<?php

declare(strict_types=1);

namespace App\Services\Scrapers\Adapters;

use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct Yandex XML API adapter.
 * Uses https://yandex.ru/search/xml endpoint with user/key credentials.
 */
final class YandexXmlAdapter implements SerpScraperAdapter
{
    private const RESULTS_PER_PAGE = 10;

    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $url = $this->baseUrl ?: 'https://yandex.ru/search/xml';
        $resultsPerPage = self::RESULTS_PER_PAGE;

        $baseParams = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'lr' => $request->yandexLr ?? 213,
            'sortby' => 'rlv',
            'filter' => 'none',
            'groupby' => "attr=d.mode=deep.groups-on-page={$resultsPerPage}.docs-in-group=1",
        ];

        $allResults = [];
        $page = 0;
        $maxResults = $request->limit;

        while (count($allResults) < $maxResults) {
            try {
                $response = Http::timeout(60)->get($url, array_merge($baseParams, ['page' => $page]));
                $pageResults = $this->parseXml($response->body(), count($allResults));

                if (empty($pageResults)) {
                    break;
                }

                $allResults = array_merge($allResults, $pageResults);

                if (count($pageResults) < $resultsPerPage) {
                    break;
                }

                $page++;
            } catch (\Exception $e) {
                Log::warning('YandexXml page fetch failed', [
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
    private function parseXml(string $xml, int $positionOffset = 0): array
    {
        $results = [];
        try {
            $doc = new \SimpleXMLElement($xml);
            if (isset($doc->response->error)) {
                return [];
            }

            $position = $positionOffset;
            foreach ($doc->response->results->grouping->group ?? [] as $group) {
                $position++;
                $d = $group->doc;
                if (! $d) {
                    continue;
                }

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
        } catch (\Exception) {
        }

        return $results;
    }

    public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse
    {
        throw new \BadMethodCallException('scrapePage is not supported by YandexXmlAdapter');
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
