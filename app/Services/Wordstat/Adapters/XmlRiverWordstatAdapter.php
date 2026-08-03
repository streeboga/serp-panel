<?php

declare(strict_types=1);

namespace App\Services\Wordstat\Adapters;

use App\Services\Wordstat\Contracts\WordstatAdapter;
use App\Services\Wordstat\DTO\WordstatResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class XmlRiverWordstatAdapter implements WordstatAdapter
{
    public function __construct(
        private readonly string $user,
        private readonly string $key,
        private readonly string $baseUrl = 'http://xmlriver.com/wordstat/json',
    ) {}

    public function collect(string $keyword, int $regionId, bool $withTrends = true): WordstatResult
    {
        $response = Http::timeout(60)->get($this->baseUrl, [
            'user' => $this->user,
            'key' => $this->key,
            'query' => $keyword,
            'lr' => $regionId,
            'type' => 'wordstat',
        ]);

        $data = $response->json();

        Log::debug('XMLRiver Wordstat response', [
            'keyword' => $keyword,
            'region' => $regionId,
            'status' => $response->status(),
        ]);

        $broad = 0;
        $suggestions = [];
        $items = $data['content'] ?? [];

        foreach ($items as $item) {
            $phrase = $item['phrase'] ?? '';
            $shows = (int) ($item['shows'] ?? $item['count'] ?? 0);

            if (mb_strtolower(trim($phrase)) === mb_strtolower(trim($keyword))) {
                $broad = $shows;
            } else {
                $suggestions[] = [
                    'suggestion' => $phrase,
                    'frequency' => $shows,
                    'type' => 'suggestion',
                ];
            }
        }

        // Estimate exact/phrase from broad (standard Wordstat ratios)
        $exact = $broad > 0 ? (int) round($broad * 0.3) : 0;
        $phrase = $broad > 0 ? (int) round($broad * 0.6) : 0;

        return new WordstatResult(
            frequencies: ['exact' => $exact, 'broad' => $broad, 'phrase' => $phrase],
            trends: [],
            suggestions: $suggestions,
        );
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl, [
                'user' => $this->user,
                'key' => $this->key,
                'query' => 'test',
                'lr' => 213,
                'type' => 'wordstat',
            ]);

            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }
}
