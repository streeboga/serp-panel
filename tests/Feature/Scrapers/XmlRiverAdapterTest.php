<?php

use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use Illuminate\Support\Facades\Http;

test('xmlriver adapter parses response correctly', function () {
    Http::fake([
        '*/search*' => Http::response(json_encode([
            'results' => [
                ['url' => 'https://example.com/page1', 'title' => 'Page 1', 'snippet' => 'Desc 1', 'type' => 'organic'],
                ['url' => 'https://other.com/page2', 'title' => 'Page 2', 'snippet' => 'Desc 2', 'type' => 'organic'],
            ],
            'total_results' => 1000,
        ])),
    ]);

    $adapter = new XmlRiverAdapter('https://xmlriver.example.com', ['user' => 'u', 'key' => 'k']);

    $response = $adapter->scrape(new ScrapeRequest(
        keyword: 'test query',
        engine: 'google',
        device: 'desktop',
        regionId: 1,
        googleGl: 'ru',
        googleHl: 'ru',
    ));

    expect($response->results)->toHaveCount(2);
    expect($response->results[0]->domain)->toBe('example.com');
    expect($response->results[0]->position)->toBe(1);
    expect($response->totalResults)->toBe(1000);
});

test('xmlriver adapter handles empty response', function () {
    Http::fake(['*/search*' => Http::response('{}')]);

    $adapter = new XmlRiverAdapter('https://xmlriver.example.com', ['user' => 'u', 'key' => 'k']);

    $response = $adapter->scrape(new ScrapeRequest(
        keyword: 'nothing',
        engine: 'google',
        device: 'desktop',
        regionId: 1,
    ));

    expect($response->results)->toBeEmpty();
});
