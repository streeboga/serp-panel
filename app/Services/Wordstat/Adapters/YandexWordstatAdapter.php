<?php

declare(strict_types=1);

namespace App\Services\Wordstat\Adapters;

use App\Services\Wordstat\Contracts\WordstatAdapter;
use App\Services\Wordstat\DTO\WordstatResult;
use Illuminate\Support\Facades\Http;

final class YandexWordstatAdapter implements WordstatAdapter
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function collect(string $keyword, int $regionId): WordstatResult
    {
        // Use Yandex Direct API v5 to get keyword forecasts
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept-Language' => 'ru',
        ])->post('https://api.direct.yandex.com/json/v5/keywords/forecast', [
            'method' => 'get',
            'params' => [
                'SelectionCriteria' => [
                    'Keywords' => [$keyword],
                ],
                'GeoIds' => [$regionId],
            ],
        ]);

        $data = $response->json();

        // Parse response
        $exact = 0;
        $broad = 0;
        $phrase = 0;

        if (isset($data['result'])) {
            foreach ($data['result'] as $item) {
                $exact = $item['Impressions'] ?? 0;
                $broad = (int) ($exact * 1.8);
                $phrase = (int) ($exact * 0.6);
            }
        }

        return new WordstatResult(
            frequencies: ['exact' => $exact, 'broad' => $broad, 'phrase' => $phrase],
        );
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get('https://api.direct.yandex.com/json/v5/clients');

            return $response->ok();
        } catch (\Exception) {
            return false;
        }
    }
}
