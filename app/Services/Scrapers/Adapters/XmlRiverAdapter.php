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

    private const MAX_PAGES = 15;

    private const MAX_ATTEMPTS = 3;

    /** @param array<string, mixed> $credentials */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $url = $this->buildUrl();
        $baseParams = $this->buildParams($request);
        $firstPage = $request->engine === 'yandex' ? 0 : 1;

        $collected = [];
        $seen = [];
        $page = $firstPage;
        $maxResults = $request->limit;
        $totalFound = 0;
        $lastPage = $firstPage + self::MAX_PAGES;

        while (count($collected) < $maxResults && $page < $lastPage) {
            $xml = $this->fetchPageXml($url, array_merge($baseParams, ['page' => $page]));

            if ($xml === null) {
                break;
            }

            if ($page === $firstPage) {
                $totalFound = $this->parseTotalFound($xml);
            }

            // A page that repeats the previous one means the provider stopped
            // paginating — keep going only while genuinely new URLs arrive.
            $new = 0;
            foreach ($this->parseXmlResponse($xml) as $item) {
                if (isset($seen[$item->url])) {
                    continue;
                }
                $seen[$item->url] = true;
                $collected[] = $item;
                $new++;
            }

            if ($new === 0) {
                break;
            }

            $page++;
        }

        return new ScrapeResponse(
            results: $this->renumber(array_slice($collected, 0, $maxResults)),
            totalResults: $totalFound ?: count($collected),
            rawResponse: '',
        );
    }

    /**
     * XMLRiver answers HTTP 200 with `<error code="500">Выполните перезапрос</error>`
     * when the search engine did not answer — that is a retry request, not an
     * empty SERP. Returns null once retries are exhausted.
     *
     * @param  array<string, mixed>  $params
     */
    private function fetchPageXml(string $url, array $params): ?string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $xml = Http::timeout(60)->get($url, $params)->body();

                if (! $this->hasError($xml)) {
                    return $xml;
                }
            } catch (\Exception $e) {
                Log::warning('XMLRiver page fetch failed', [
                    'page' => $params['page'] ?? null,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep($attempt);
            }
        }

        return null;
    }

    private function hasError(string $xml): bool
    {
        try {
            return isset((new \SimpleXMLElement($xml))->response->error);
        } catch (\Exception) {
            return true;
        }
    }

    /**
     * @param  SerpResultItem[]  $items
     * @return SerpResultItem[]
     */
    private function renumber(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            $out[] = new SerpResultItem(
                position: count($out) + 1,
                url: $item->url,
                domain: $item->domain,
                title: $item->title,
                description: $item->description,
                snippetType: $item->snippetType,
                isAds: $item->isAds,
            );
        }

        return $out;
    }

    public function supportsPageScraping(): bool
    {
        return true;
    }

    public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse
    {
        $url = $this->buildUrl();
        $params = $this->buildParams($request);
        $firstPage = $request->engine === 'yandex' ? 0 : 1;
        $positionOffset = ($page - $firstPage) * self::RESULTS_PER_PAGE;

        $response = Http::timeout(60)->get($url, array_merge($params, ['page' => $page]));
        $xml = $response->body();

        $totalFound = $this->parseTotalFound($xml);
        $results = $this->parseXmlResponse($xml, $positionOffset);

        return new ScrapeResponse(
            results: $results,
            totalResults: $totalFound,
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
            $url = $this->buildUrl();

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

    private function buildUrl(): string
    {
        $url = rtrim($this->baseUrl, '/');
        if (! str_contains($url, '/search')) {
            $url .= '/search/xml';
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function buildParams(ScrapeRequest $request): array
    {
        $params = [
            'user' => $this->credentials['user'] ?? '',
            'key' => $this->credentials['key'] ?? '',
            'query' => $request->keyword,
            'groupby' => self::RESULTS_PER_PAGE,
        ];

        if ($request->engine === 'yandex') {
            $params['lr'] = $request->yandexLr;
        } else {
            $params['gl'] = $request->googleGl;
            $params['hl'] = $request->googleHl;
        }

        return $params;
    }

    private function parseTotalFound(string $xml): int
    {
        try {
            $doc = new \SimpleXMLElement($xml);

            foreach ($doc->response->found ?? [] as $found) {
                if ((string) $found['priority'] === 'all') {
                    return (int) (string) $found;
                }
            }
        } catch (\Exception) {
        }

        return 0;
    }
}
