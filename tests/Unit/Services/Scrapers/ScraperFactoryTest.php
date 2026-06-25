<?php

declare(strict_types=1);

use App\Models\Scraper;
use App\Services\Scrapers\Adapters\WebhookAdapter;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\Adapters\YandexCloudSearchAdapter;
use App\Services\Scrapers\Adapters\YandexXmlAdapter;
use App\Services\Scrapers\ScraperFactory;

covers(ScraperFactory::class);

test('types include yandex_cloud with yandex engine', function () {
    $types = ScraperFactory::types();

    expect($types)->toHaveKey('yandex_cloud');
    expect($types['yandex_cloud']['engines'])->toBe(['yandex']);
});

test('make resolves each type to its adapter', function (string $type, string $adapterClass) {
    $scraper = new Scraper([
        'type' => $type,
        'base_url' => 'https://example.test',
        'credentials' => ['folder_id' => 'b1g', 'api_key' => 'AQVN', 'user' => 'u', 'key' => 'k'],
    ]);

    expect((new ScraperFactory)->make($scraper))->toBeInstanceOf($adapterClass);
})->with([
    ['xmlriver', XmlRiverAdapter::class],
    ['yandex_xml', YandexXmlAdapter::class],
    ['yandex_cloud', YandexCloudSearchAdapter::class],
    ['webhook', WebhookAdapter::class],
]);

test('make throws on unknown type', function () {
    $scraper = new Scraper(['type' => 'nope', 'base_url' => 'https://x.test']);

    expect(fn () => (new ScraperFactory)->make($scraper))->toThrow(InvalidArgumentException::class);
});
