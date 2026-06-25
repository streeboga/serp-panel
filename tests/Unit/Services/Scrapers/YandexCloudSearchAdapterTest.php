<?php

declare(strict_types=1);

use App\Services\Scrapers\Adapters\YandexCloudSearchAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use Illuminate\Support\Facades\Http;

covers(YandexCloudSearchAdapter::class);

function makeCloudAdapter(): YandexCloudSearchAdapter
{
    return new YandexCloudSearchAdapter(
        baseUrl: '',
        credentials: ['folder_id' => 'b1gfolder', 'api_key' => 'AQVNtest'],
    );
}

function makeCloudRequest(int $limit = 10): ScrapeRequest
{
    return new ScrapeRequest(
        keyword: 'купить квартиру',
        engine: 'yandex',
        device: 'desktop',
        regionId: 213,
        limit: $limit,
        yandexLr: 213,
    );
}

function buildCloudXml(int $count = 10): string
{
    $groups = '';
    for ($i = 1; $i <= $count; $i++) {
        $groups .= <<<XML
            <group>
                <doc>
                    <url>https://example.com/page-{$i}</url>
                    <domain>example.com</domain>
                    <title>Page {$i}</title>
                    <passages><passage>Description {$i}</passage></passages>
                </doc>
            </group>
        XML;
    }

    return <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <yandexsearch version="1.0">
        <response>
            <results>
                <grouping>
                    {$groups}
                </grouping>
            </results>
        </response>
    </yandexsearch>
    XML;
}

function fakeCloudOperation(string $xml): void
{
    Http::fake([
        'searchapi.api.cloud.yandex.net/*' => Http::response(['id' => 'op-123', 'done' => false]),
        'operation.api.cloud.yandex.net/*' => Http::response([
            'id' => 'op-123',
            'done' => true,
            'response' => ['rawData' => base64_encode($xml)],
        ]),
    ]);
}

test('scrapePage submits async search, polls operation and parses xml', function () {
    fakeCloudOperation(buildCloudXml(10));

    $response = makeCloudAdapter()->scrapePage(makeCloudRequest(), 0);

    expect($response->results)->toHaveCount(10);
    expect($response->results[0]->url)->toBe('https://example.com/page-1');
    expect($response->results[0]->domain)->toBe('example.com');
    expect($response->results[0]->title)->toBe('Page 1');
    expect($response->results[0]->position)->toBe(1);
    expect($response->results[9]->position)->toBe(10);
});

test('scrape returns results from decoded operation payload', function () {
    fakeCloudOperation(buildCloudXml(10));

    $response = makeCloudAdapter()->scrape(makeCloudRequest(10));

    expect($response->results)->toHaveCount(10);
    expect($response->totalResults)->toBe(10);
});

test('sends Api-Key auth header and folderId in body', function () {
    fakeCloudOperation(buildCloudXml(3));

    makeCloudAdapter()->scrapePage(makeCloudRequest(), 0);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'searchAsync')) {
            return true; // ignore operation polling requests
        }

        return $request->header('Authorization')[0] === 'Api-Key AQVNtest'
            && $request['folderId'] === 'b1gfolder'
            && $request['query']['queryText'] === 'купить квартиру';
    });
});

test('healthCheck returns false without credentials', function () {
    $adapter = new YandexCloudSearchAdapter(baseUrl: '', credentials: []);

    expect($adapter->healthCheck())->toBeFalse();
});

test('healthCheck succeeds when submit returns operation id', function () {
    Http::fake([
        'searchapi.api.cloud.yandex.net/*' => Http::response(['id' => 'op-xyz', 'done' => false]),
    ]);

    expect(makeCloudAdapter()->healthCheck())->toBeTrue();
});

test('supports yandex engine only', function () {
    expect(makeCloudAdapter()->supportedEngines())->toBe(['yandex']);
});
