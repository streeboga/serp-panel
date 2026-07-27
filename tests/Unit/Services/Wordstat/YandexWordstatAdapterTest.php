<?php

declare(strict_types=1);

use App\Services\Wordstat\Adapters\YandexWordstatAdapter;
use Illuminate\Support\Facades\Http;

covers(YandexWordstatAdapter::class);

function makeWordstatAdapter(): YandexWordstatAdapter
{
    return new YandexWordstatAdapter(apiKey: 'AQVNtest', folderId: 'b1gfolder');
}

test('collect parses topRequests frequencies, suggestions and dynamics trends', function () {
    Http::fake([
        '*/v2/wordstat/topRequests' => Http::response([
            'totalCount' => '878846',
            'results' => [
                ['phrase' => 'купить квартиру', 'count' => '878846'],
                ['phrase' => 'купить квартиру в москве', 'count' => '146596'],
            ],
            'associations' => [
                ['phrase' => 'квартиры новостройки', 'count' => '12000'],
            ],
        ]),
        '*/v2/wordstat/dynamics' => Http::response([
            'results' => [
                ['date' => '2025-06-01T00:00:00Z', 'count' => '1025972', 'share' => 0.08],
                ['date' => '2025-07-01T00:00:00Z', 'count' => '1118257', 'share' => 0.09],
            ],
        ]),
    ]);

    $result = makeWordstatAdapter()->collect('купить квартиру', 213);

    expect($result->frequencies['broad'])->toBe(878846);
    // Phrase/exact are separate measurements, never derived from broad — unknown
    // until precise collection is enabled.
    expect($result->frequencies['phrase'])->toBeNull();
    expect($result->frequencies['exact'])->toBeNull();

    // exact-match phrase excluded from suggestions; one top + one association remain
    expect($result->suggestions)->toHaveCount(2);
    expect($result->suggestions[0]['suggestion'])->toBe('купить квартиру в москве');
    expect($result->suggestions[1]['type'])->toBe('association');

    expect($result->trends)->toHaveCount(2);
    expect($result->trends[strtotime('2025-06-01T00:00:00Z')])->toBe(1025972);
});

test('collect sends Api-Key auth and folderId + region in body', function () {
    Http::fake(['*' => Http::response(['totalCount' => '0', 'results' => []])]);

    makeWordstatAdapter()->collect('тест', 213);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'topRequests')) {
            return true;
        }

        return $request->header('Authorization')[0] === 'Api-Key AQVNtest'
            && $request['folderId'] === 'b1gfolder'
            && $request['regions'] === ['213']
            && $request['phrase'] === 'тест';
    });
});

test('collect returns no frequencies on API error', function () {
    Http::fake(['*' => Http::response(['message' => 'forbidden'], 403)]);

    $result = makeWordstatAdapter()->collect('тест', 213);

    expect($result->frequencies)->toBe(['exact' => null, 'broad' => 0, 'phrase' => null]);
    expect($result->suggestions)->toBeEmpty();
});

test('healthCheck returns false without credentials', function () {
    expect((new YandexWordstatAdapter('', ''))->healthCheck())->toBeFalse();
});

test('healthCheck hits getRegionsTree with Api-Key', function () {
    Http::fake(['*/v2/wordstat/getRegionsTree' => Http::response(['regions' => []])]);

    expect(makeWordstatAdapter()->healthCheck())->toBeTrue();
});
