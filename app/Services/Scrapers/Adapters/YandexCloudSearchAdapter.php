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
 * Yandex Cloud Search API v2 adapter.
 *
 * Auth: `Authorization: Api-Key <api_key>` + `folderId`.
 * Flow is asynchronous: POST /v2/web/searchAsync returns an Operation, which is
 * polled at operation.api.cloud.yandex.net until `done`, then `response.rawData`
 * holds base64-encoded Yandex XML (same schema as the Yandex XML API).
 *
 * @see https://yandex.cloud/docs/search-api/
 */
final class YandexCloudSearchAdapter implements SerpScraperAdapter
{
    private const SEARCH_URL = 'https://searchapi.api.cloud.yandex.net/v2/web/searchAsync';

    private const OPERATION_URL = 'https://operation.api.cloud.yandex.net/operations/';

    private const MAX_GROUPS_ON_PAGE = 100;

    private const DEFAULT_REGION = 213; // Moscow

    private const POLL_TIMEOUT_SECONDS = 90;

    /** @param array<string, mixed> $credentials */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $credentials,
    ) {}

    public function scrape(ScrapeRequest $request): ScrapeResponse
    {
        $maxResults = $request->limit;
        $maxPages = (int) ceil($maxResults / $this->groupsOnPage($maxResults));

        $allResults = [];
        $seen = [];
        $rawPages = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $xml = $this->fetchPage($request, $page);

            if ($xml === null) {
                break;
            }

            $rawPages[] = $xml;

            // Pages can come back short (9 of 10) without meaning the SERP ended —
            // keep paging while genuinely new URLs arrive.
            $new = 0;
            foreach ($this->parseXml($xml) as $item) {
                if (isset($seen[$item->url])) {
                    continue;
                }
                $seen[$item->url] = true;
                $allResults[] = $item;
                $new++;
            }

            if ($new === 0) {
                break;
            }
        }

        return new ScrapeResponse(
            results: $this->renumber(array_slice($allResults, 0, $maxResults)),
            totalResults: count($allResults),
            rawResponse: implode("\n", $rawPages),
        );
    }

    private function fetchPage(ScrapeRequest $request, int $page): ?string
    {
        $apiKey = (string) ($this->credentials['api_key'] ?? '');
        $folderId = (string) ($this->credentials['folder_id'] ?? '');
        $region = $request->yandexLr ?? self::DEFAULT_REGION;

        try {
            $submit = Http::withHeaders(['Authorization' => "Api-Key {$apiKey}"])
                ->timeout(30)
                ->post($this->baseUrl ?: self::SEARCH_URL, [
                    'query' => [
                        'searchType' => 'SEARCH_TYPE_RU',
                        'queryText' => $request->keyword,
                        'page' => (string) $page,
                    ],
                    'folderId' => $folderId,
                    'responseFormat' => 'FORMAT_XML',
                    'region' => (string) $region,
                    'l10n' => 'LOCALIZATION_RU',
                    // The API serves up to 100 results in one response, so the whole
                    // top-100 is a single async round-trip instead of ten.
                    'groupSpec' => [
                        'groupsOnPage' => (string) $this->groupsOnPage($request->limit),
                        'docsInGroup' => '1',
                    ],
                ]);

            if (! $submit->successful()) {
                Log::warning('YandexCloudSearch submit failed', [
                    'page' => $page,
                    'status' => $submit->status(),
                    'body' => mb_substr($submit->body(), 0, 500),
                ]);

                return null;
            }

            $operationId = (string) $submit->json('id', '');

            if ($operationId === '') {
                return null;
            }

            return $this->awaitOperation($operationId, $apiKey);
        } catch (\Throwable $e) {
            Log::warning('YandexCloudSearch page fetch failed', [
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function awaitOperation(string $operationId, string $apiKey): ?string
    {
        $deadline = time() + self::POLL_TIMEOUT_SECONDS;
        $attempt = 0;

        do {
            usleep($attempt === 0 ? 1_000_000 : 2_000_000);
            $attempt++;

            $operation = Http::withHeaders(['Authorization' => "Api-Key {$apiKey}"])
                ->timeout(30)
                ->get(self::OPERATION_URL.$operationId);

            if (! $operation->successful()) {
                return null;
            }

            if ($operation->json('error') !== null) {
                Log::warning('YandexCloudSearch operation error', [
                    'operation' => $operationId,
                    'error' => $operation->json('error'),
                ]);

                return null;
            }

            if ($operation->json('done') === true) {
                $rawData = $operation->json('response.rawData');

                if (! is_string($rawData) || $rawData === '') {
                    return null;
                }

                $decoded = base64_decode($rawData, true);

                return $decoded === false ? null : $decoded;
            }
        } while (time() < $deadline);

        Log::warning('YandexCloudSearch operation timed out', ['operation' => $operationId]);

        return null;
    }

    /** Results per request; the API caps this at 100. */
    private function groupsOnPage(int $limit): int
    {
        return max(1, min($limit, self::MAX_GROUPS_ON_PAGE));
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
                    domain: (string) ($d->domain ?? '') ?: (parse_url($url, PHP_URL_HOST) ?: ''),
                    title: trim(strip_tags((string) ($d->title ?? ''))),
                    description: trim(strip_tags((string) ($d->passages->passage ?? $d->headline ?? ''))),
                    snippetType: 'organic',
                    isAds: false,
                );
            }
        } catch (\Throwable) {
        }

        return $results;
    }

    public function supportsPageScraping(): bool
    {
        return true;
    }

    public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse
    {
        $xml = $this->fetchPage($request, $page);

        if ($xml === null) {
            return new ScrapeResponse(results: [], totalResults: 0, rawResponse: '');
        }

        $results = $this->parseXml($xml);

        return new ScrapeResponse(
            results: $results,
            totalResults: count($results),
            rawResponse: $xml,
        );
    }

    /** @return string[] */
    public function supportedEngines(): array
    {
        return ['yandex'];
    }

    public function healthCheck(): bool
    {
        $apiKey = (string) ($this->credentials['api_key'] ?? '');
        $folderId = (string) ($this->credentials['folder_id'] ?? '');

        if ($apiKey === '' || $folderId === '') {
            return false;
        }

        try {
            $submit = Http::withHeaders(['Authorization' => "Api-Key {$apiKey}"])
                ->timeout(15)
                ->post($this->baseUrl ?: self::SEARCH_URL, [
                    'query' => [
                        'searchType' => 'SEARCH_TYPE_RU',
                        'queryText' => 'test',
                        'page' => '0',
                    ],
                    'folderId' => $folderId,
                    'responseFormat' => 'FORMAT_XML',
                ]);

            return $submit->successful() && $submit->json('id') !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
