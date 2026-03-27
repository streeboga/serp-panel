<?php

declare(strict_types=1);

namespace App\Services\Wordstat\Adapters;

use App\Services\Wordstat\Contracts\WordstatAdapter;
use App\Services\Wordstat\DTO\WordstatResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YandexWordstatAdapter implements WordstatAdapter
{
    private const BASE_URL = 'https://api.wordstat.yandex.net/v1';

    public function __construct(
        private readonly string $token,
    ) {}

    public function collect(string $keyword, int $regionId): WordstatResult
    {
        $frequencies = $this->fetchTopRequests($keyword, $regionId);
        $trends = $this->fetchDynamics($keyword, $regionId);
        $suggestions = $frequencies['suggestions'];

        return new WordstatResult(
            frequencies: $frequencies['frequencies'],
            trends: $trends,
            suggestions: $suggestions,
        );
    }

    /**
     * @return array{frequencies: array{exact: int, broad: int, phrase: int}, suggestions: list<array{suggestion: string, frequency: int, type: string}>}
     */
    private function fetchTopRequests(string $keyword, int $regionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json; charset=utf-8',
        ])->post(self::BASE_URL . '/topRequests', [
            'phrase' => $keyword,
            'regions' => [$regionId],
        ]);

        $data = $response->json();

        Log::debug('Wordstat topRequests', [
            'keyword' => $keyword,
            'region' => $regionId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        $broad = 0;
        $exact = 0;
        $phrase = 0;
        $suggestions = [];

        $items = $data['topRequests'] ?? $data['data'] ?? [];

        foreach ($items as $item) {
            $itemPhrase = $item['phrase'] ?? $item['text'] ?? '';
            $count = (int) ($item['count'] ?? $item['shows'] ?? 0);

            if (mb_strtolower(trim($itemPhrase)) === mb_strtolower(trim($keyword))) {
                $broad = $count;
            } else {
                $suggestions[] = [
                    'suggestion' => $itemPhrase,
                    'frequency' => $count,
                    'type' => 'suggestion',
                ];
            }
        }

        // Exact/phrase estimates from broad
        if ($broad > 0) {
            $exact = (int) round($broad * 0.3);
            $phrase = (int) round($broad * 0.6);
        }

        return [
            'frequencies' => ['exact' => $exact, 'broad' => $broad, 'phrase' => $phrase],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @return array<int, int> key = Unix timestamp, value = absolute count
     */
    private function fetchDynamics(string $keyword, int $regionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post(self::BASE_URL . '/dynamics', [
                'phrase' => $keyword,
                'regions' => [$regionId],
            ]);

            $data = $response->json();
            $trends = [];

            $items = $data['dynamics'] ?? $data['data'] ?? [];
            foreach ($items as $item) {
                $date = $item['date'] ?? $item['period'] ?? null;
                $count = (int) ($item['count'] ?? $item['shows'] ?? $item['value'] ?? 0);

                if ($date !== null) {
                    $ts = strtotime($date);
                    if ($ts !== false) {
                        $trends[$ts] = $count;
                    }
                }
            }

            return $trends;
        } catch (\Exception $e) {
            Log::warning('Wordstat dynamics failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post(self::BASE_URL . '/userInfo', []);

            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }
}
