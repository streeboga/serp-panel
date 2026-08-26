<?php

declare(strict_types=1);

use App\Contracts\Repositories\DomainIndexResultRepositoryInterface;
use App\Contracts\Repositories\DomainRepositoryInterface;
use App\Contracts\Repositories\ScraperRepositoryInterface;
use App\Jobs\FetchIndexPageJob;
use App\Models\DomainIndexResult;
use App\Models\Scraper;
use App\Services\Scrapers\Contracts\SerpScraperAdapter;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

covers(FetchIndexPageJob::class);

function fetchPageFactory(ScrapeResponse $response, bool $once = false): ScraperFactory
{
    $adapter = Mockery::mock(SerpScraperAdapter::class);
    $expectation = $adapter->shouldReceive('scrapePage')->andReturn($response);

    if ($once) {
        $expectation->once();
    }

    $factory = Mockery::mock(ScraperFactory::class)->makePartial();
    $factory->shouldReceive('make')->andReturn($adapter);

    return $factory;
}

function runFetchPageJob(FetchIndexPageJob $job, ScraperFactory $factory): void
{
    $job->handle(
        app(DomainRepositoryInterface::class),
        app(ScraperRepositoryInterface::class),
        app(DomainIndexResultRepositoryInterface::class),
        $factory,
    );
}

test('fetches page and upserts results', function () {
    $stack = createFullStack();
    Scraper::create([
        'organization_id' => $stack['org']->id,
        'name' => 'XMLRiver',
        'type' => 'xmlriver',
        'base_url' => 'https://xmlriver.test',
        'credentials' => ['user' => 'u', 'key' => 'k'],
        'is_active' => true,
        'supported_engines' => ['google'],
    ]);

    $results = [
        new SerpResultItem(11, 'https://example.com/p11', 'example.com', 'P11', 'Desc', 'organic', false),
        new SerpResultItem(12, 'https://example.com/p12', 'example.com', 'P12', 'Desc', 'organic', false),
    ];

    $mockFactory = fetchPageFactory(new ScrapeResponse(results: $results, totalResults: 350), once: true);

    $job = new FetchIndexPageJob(
        domainId: $stack['domain']->id,
        engine: 'google',
        page: 2,
        collectedAt: now()->toDateString(),
    );

    runFetchPageJob($job, $mockFactory);

    expect(DomainIndexResult::where('domain_id', $stack['domain']->id)->count())->toBe(2);

    $result = DomainIndexResult::where('url', 'https://example.com/p11')->first();
    expect($result->last_position)->toBe(11);
    expect($result->first_seen_at->toDateString())->toBe(now()->toDateString());
    expect($result->last_seen_at->toDateString())->toBe(now()->toDateString());
});

test('upsert updates existing rows without duplicating', function () {
    $stack = createFullStack();
    Scraper::create([
        'organization_id' => $stack['org']->id,
        'name' => 'XMLRiver',
        'type' => 'xmlriver',
        'base_url' => 'https://xmlriver.test',
        'credentials' => ['user' => 'u', 'key' => 'k'],
        'is_active' => true,
        'supported_engines' => ['google'],
    ]);

    // Pre-existing row
    DomainIndexResult::create([
        'domain_id' => $stack['domain']->id,
        'url' => 'https://example.com/p11',
        'engine' => 'google',
        'last_position' => 50,
        'first_seen_at' => '2026-03-20',
        'last_seen_at' => '2026-03-20',
    ]);

    $results = [
        new SerpResultItem(11, 'https://example.com/p11', 'example.com', 'P11', 'Desc', 'organic', false),
    ];

    $mockFactory = fetchPageFactory(new ScrapeResponse(results: $results, totalResults: 100));

    $job = new FetchIndexPageJob(
        domainId: $stack['domain']->id,
        engine: 'google',
        page: 2,
        collectedAt: '2026-03-27',
    );

    runFetchPageJob($job, $mockFactory);

    // Should still be 1 row, not 2
    expect(DomainIndexResult::where('domain_id', $stack['domain']->id)->count())->toBe(1);

    $result = DomainIndexResult::where('url', 'https://example.com/p11')->first();
    expect($result->last_position)->toBe(11); // Updated
    expect($result->first_seen_at->toDateString())->toBe('2026-03-20'); // Preserved!
    expect($result->last_seen_at->toDateString())->toBe('2026-03-27'); // Updated
});

test('has rate limited middleware', function () {
    $job = new FetchIndexPageJob(domainId: 1, engine: 'google', page: 1, collectedAt: '2026-03-27');

    expect($job->middleware())->toHaveCount(1);
    expect($job->middleware()[0])->toBeInstanceOf(RateLimitedWithRedis::class);
});

test('is on indexing queue', function () {
    $job = new FetchIndexPageJob(domainId: 1, engine: 'google', page: 1, collectedAt: '2026-03-27');

    expect($job->queue)->toBe('indexing');
});
