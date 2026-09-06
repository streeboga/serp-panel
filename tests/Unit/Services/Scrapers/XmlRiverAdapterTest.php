<?php

declare(strict_types=1);

use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use Illuminate\Support\Facades\Http;

covers(XmlRiverAdapter::class);

function makeAdapter(): XmlRiverAdapter
{
    return new XmlRiverAdapter(
        baseUrl: 'https://xmlriver.com',
        credentials: ['user' => 'test-user', 'key' => 'test-key'],
    );
}

function makeRequest(string $engine = 'yandex'): ScrapeRequest
{
    return new ScrapeRequest(
        keyword: 'купить диван',
        engine: $engine,
        device: 'desktop',
        regionId: 213,
        limit: 100,
        yandexLr: 213,
        googleGl: 'ru',
        googleHl: 'ru',
    );
}

function buildXmlResponse(int $resultsCount = 10, int $totalFound = 350, int $urlOffset = 0): string
{
    $groups = '';
    for ($i = 1; $i <= $resultsCount; $i++) {
        $n = $i + $urlOffset;
        $groups .= <<<XML
                <group>
                    <doc>
                        <url>https://example.com/page-{$n}</url>
                        <title>Page {$i}</title>
                        <passages><passage>Description {$i}</passage></passages>
                        <contenttype>organic</contenttype>
                    </doc>
                </group>
        XML;
    }

    return <<<XML
    <yandexsearch>
        <response>
            <found priority="all">{$totalFound}</found>
            <results>
                <grouping>
                    {$groups}
                </grouping>
            </results>
        </response>
    </yandexsearch>
    XML;
}

test('scrape page returns single page results', function () {
    Http::fake([
        'xmlriver.com/*' => Http::response(buildXmlResponse(10, 350)),
    ]);

    $adapter = makeAdapter();
    $response = $adapter->scrapePage(makeRequest(), 0);

    expect($response->results)->toHaveCount(10);
    expect($response->totalResults)->toBe(350);
    expect($response->results[0]->url)->toBe('https://example.com/page-1');
    expect($response->results[0]->position)->toBe(1);
    expect($response->results[9]->position)->toBe(10);
});

test('scrape page calculates position offset', function () {
    Http::fake([
        'xmlriver.com/*' => Http::response(buildXmlResponse(10, 350)),
    ]);

    $adapter = makeAdapter();
    // Yandex firstPage=0, so page 5 offset = (5-0)*10 = 50
    $response = $adapter->scrapePage(makeRequest('yandex'), 5);

    expect($response->results[0]->position)->toBe(51);
    expect($response->results[9]->position)->toBe(60);
});

test('scrape page returns zero total on empty response', function () {
    $emptyXml = <<<'XML'
    <yandexsearch>
        <response>
            <found priority="all">0</found>
            <results>
                <grouping>
                </grouping>
            </results>
        </response>
    </yandexsearch>
    XML;

    Http::fake([
        'xmlriver.com/*' => Http::response($emptyXml),
    ]);

    $adapter = makeAdapter();
    $response = $adapter->scrapePage(makeRequest(), 0);

    expect($response->results)->toBeEmpty();
    expect($response->totalResults)->toBe(0);
});

test('scrape returns total found from xml', function () {
    // Distinct URLs per page: page 1 = 1..10, page 2 = 11..15, then the sequence
    // repeats page 2 so pagination stops on a page with no new URLs.
    Http::fake([
        'xmlriver.com/*' => Http::sequence()
            ->push(buildXmlResponse(10, 350))
            ->push(buildXmlResponse(5, 350, 10))
            ->push(buildXmlResponse(5, 350, 10)),
    ]);

    $adapter = makeAdapter();
    $request = makeRequest();
    $request = new ScrapeRequest(
        keyword: 'купить диван',
        engine: 'yandex',
        device: 'desktop',
        regionId: 213,
        limit: 20,
        yandexLr: 213,
    );
    $response = $adapter->scrape($request);

    expect($response->totalResults)->toBe(350);
    expect($response->results)->toHaveCount(15);
});

test('a short page (9 of 10) does not stop pagination', function () {
    // XMLRiver commonly returns 9 results on a page; the old code treated that as
    // "end of SERP" and only ever collected the first page, so anything below the
    // top ~9 was reported as not ranking.
    Http::fake([
        'xmlriver.com/*' => Http::sequence()
            ->push(buildXmlResponse(9, 350))
            ->push(buildXmlResponse(9, 350, 9))
            ->push(buildXmlResponse(9, 350, 18))
            ->push(buildXmlResponse(9, 350, 18)),
    ]);

    $response = makeAdapter()->scrape(new ScrapeRequest(
        keyword: 'поддержка laravel', engine: 'yandex', device: 'desktop',
        regionId: 213, limit: 100, yandexLr: 213,
    ));

    expect($response->results)->toHaveCount(27)
        ->and($response->results[26]->position)->toBe(27);
});

test('a transient provider error yields no results instead of a fake empty SERP', function () {
    $error = '<yandexsearch><response><error code="500">Выполните перезапрос.</error></response></yandexsearch>';
    Http::fake(['xmlriver.com/*' => Http::response($error)]);

    expect(makeAdapter()->scrape(makeRequest())->results)->toBeEmpty();
});
