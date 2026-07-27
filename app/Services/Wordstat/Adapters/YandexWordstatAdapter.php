<?php

declare(strict_types=1);

namespace App\Services\Wordstat\Adapters;

use App\Services\Wordstat\Contracts\WordstatAdapter;
use App\Services\Wordstat\DTO\WordstatResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Yandex Wordstat v2 adapter.
 *
 * Wordstat v2 is served by the Yandex Cloud Search API (same host/product as web
 * search), under /v2/wordstat/*, authenticated with an `Api-Key` + `folderId`
 * (NOT an OAuth token). Requires a service account with role
 * `search-api.webSearch.user` and an API key scoped `yc.search-api.execute`.
 *
 * @see https://yandex.cloud/docs/search-api/
 */
final class YandexWordstatAdapter implements WordstatAdapter
{
    private const BASE_URL = 'https://searchapi.api.cloud.yandex.net/v2/wordstat';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $folderId,
    ) {}

    public function collect(string $keyword, int $regionId): WordstatResult
    {
        $top = $this->fetchTopRequests($keyword, $regionId);

        return new WordstatResult(
            frequencies: $top['frequencies'],
            trends: $this->fetchDynamics($keyword, $regionId),
            suggestions: $top['suggestions'],
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $body): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Api-Key '.$this->apiKey,
            'Content-Type' => 'application/json; charset=utf-8',
        ])->timeout(30)->post(self::BASE_URL.$endpoint, array_merge($body, [
            'folderId' => $this->folderId,
        ]));

        if ($response->status() === 429) {
            // Quota exhausted (100 req/hour). Throw so the job is retried later
            // within its retryUntil window instead of recording a zero frequency.
            throw new \RuntimeException('Wordstat v2 rate limit reached (429)');
        }

        if (! $response->successful()) {
            Log::warning('Wordstat v2 request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * @return array{frequencies: array{exact: int, broad: int, phrase: int}, suggestions: list<array{suggestion: string, frequency: int, type: string}>}
     */
    private function fetchTopRequests(string $keyword, int $regionId): array
    {
        $body = ['phrase' => $keyword, 'numPhrases' => 50];

        if ($regionId > 0) {
            $body['regions'] = [(string) $regionId];
        }

        $data = $this->request('/topRequests', $body);

        // totalCount = total impressions matching the phrase over the last 30 days (broad frequency).
        $broad = $this->toInt($data['totalCount'] ?? 0);

        $suggestions = [];
        foreach (($data['results'] ?? []) as $item) {
            $phrase = (string) ($item['phrase'] ?? '');
            $count = $this->toInt($item['count'] ?? 0);

            if (mb_strtolower(trim($phrase)) === mb_strtolower(trim($keyword))) {
                if ($broad === 0) {
                    $broad = $count;
                }

                continue;
            }

            $suggestions[] = ['suggestion' => $phrase, 'frequency' => $count, 'type' => 'suggestion'];
        }

        foreach (($data['associations'] ?? []) as $item) {
            $suggestions[] = [
                'suggestion' => (string) ($item['phrase'] ?? ''),
                'frequency' => $this->toInt($item['count'] ?? 0),
                'type' => 'association',
            ];
        }

        // v2 topRequests reports broad volume only. Phrase/exact need their own
        // queries with Wordstat operators, which cost extra quota — so they are
        // measured only when enabled, and left null (unknown) otherwise. They must
        // never be derived from broad: an invented number reads as a measured one.
        [$phrase, $exact] = $this->measurePhraseAndExact($keyword, $regionId, $broad);

        return [
            'frequencies' => ['exact' => $exact, 'broad' => $broad, 'phrase' => $phrase],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Real phrase ("phrase") and exact ("!word !word") volumes, each an extra
     * Wordstat call. Returns [null, null] when precise collection is disabled.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function measurePhraseAndExact(string $keyword, int $regionId, int $broad): array
    {
        if ($broad === 0 || ! config('serp.wordstat_precise', false)) {
            return [null, null];
        }

        $words = preg_split('/\s+/', trim($keyword)) ?: [];
        $exactQuery = '"'.implode(' ', array_map(static fn (string $w): string => '!'.$w, $words)).'"';

        return [
            $this->fetchPhraseVolume('"'.$keyword.'"', $regionId),
            $this->fetchPhraseVolume($exactQuery, $regionId),
        ];
    }

    private function fetchPhraseVolume(string $query, int $regionId): ?int
    {
        $body = ['phrase' => $query, 'numPhrases' => 1];

        if ($regionId > 0) {
            $body['regions'] = [(string) $regionId];
        }

        $data = $this->request('/topRequests', $body);

        return $data === [] ? null : $this->toInt($data['totalCount'] ?? 0);
    }

    /**
     * @return array<int, int> key = Unix timestamp, value = absolute count
     */
    private function fetchDynamics(string $keyword, int $regionId): array
    {
        try {
            // PERIOD_MONTHLY requires fromDate = first day of a month and
            // toDate = last day of a month, as RFC3339 timestamps.
            $to = now()->subMonthNoOverflow()->endOfMonth();
            $from = $to->copy()->subMonths(11)->startOfMonth();

            $body = [
                'phrase' => $keyword,
                'period' => 'PERIOD_MONTHLY',
                'fromDate' => $from->format('Y-m-d\T00:00:00\Z'),
                'toDate' => $to->format('Y-m-d\T00:00:00\Z'),
            ];

            if ($regionId > 0) {
                $body['regions'] = [(string) $regionId];
            }

            $data = $this->request('/dynamics', $body);

            $trends = [];
            foreach (($data['results'] ?? $data['dynamics'] ?? []) as $item) {
                $date = $item['date'] ?? $item['period'] ?? $item['fromDate'] ?? null;
                $count = $this->toInt($item['count'] ?? $item['value'] ?? 0);

                if ($date !== null) {
                    $ts = strtotime((string) $date);
                    if ($ts !== false) {
                        $trends[$ts] = $count;
                    }
                }
            }

            return $trends;
        } catch (\Throwable $e) {
            Log::warning('Wordstat v2 dynamics failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function healthCheck(): bool
    {
        if ($this->apiKey === '' || $this->folderId === '') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Api-Key '.$this->apiKey,
                'Content-Type' => 'application/json; charset=utf-8',
            ])->timeout(15)->post(self::BASE_URL.'/getRegionsTree', [
                'folderId' => $this->folderId,
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return (int) preg_replace('/[^0-9-]/', '', $value);
        }

        return (int) $value;
    }
}
