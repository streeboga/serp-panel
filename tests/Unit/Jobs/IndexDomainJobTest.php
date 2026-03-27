<?php

declare(strict_types=1);

use App\Jobs\FetchIndexPageJob;
use App\Jobs\IndexDomainJob;
use App\Models\DomainIndexResult;
use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Support\Facades\Bus;

covers(IndexDomainJob::class);

function makeResults(int $count, int $startPosition = 1): array
{
    return array_map(
        fn ($i) => new SerpResultItem($i, "https://example.com/p{$i}", 'example.com', "P{$i}", 'Desc', 'organic', false),
        range($startPosition, $startPosition + $count - 1),
    );
}

function setupScraperForDomain(array $stack): Scraper
{
    return Scraper::create([
        'organization_id' => $stack['org']->id,
        'name' => 'XMLRiver',
        'type' => 'xmlriver',
        'base_url' => 'https://xmlriver.test',
        'credentials' => ['user' => 'u', 'key' => 'k'],
        'is_active' => true,
        'supported_engines' => ['google', 'yandex'],
    ]);
}

function mockFactory(ScrapeResponse $response): ScraperFactory
{
    $mockAdapter = Mockery::mock(XmlRiverAdapter::class);
    $mockAdapter->shouldReceive('scrapePage')->andReturn($response);

    $mockFactory = Mockery::mock(ScraperFactory::class);
    $mockFactory->shouldReceive('make')->andReturn($mockAdapter);

    return $mockFactory;
}

test('dispatches batch for remaining pages', function () {
    Bus::fake([FetchIndexPageJob::class]);
    $stack = createFullStack();
    setupScraperForDomain($stack);

    $factory = mockFactory(new ScrapeResponse(results: makeResults(10), totalResults: 350));

    $job = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job->handle($factory);

    // 350 total, limit 100 → 10 pages, page 1 done → 9 batch jobs
    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 9
        && $batch->jobs->every(fn ($j) => $j instanceof FetchIndexPageJob)
    );
});

test('saves first page results via upsert', function () {
    Bus::fake([FetchIndexPageJob::class]);
    $stack = createFullStack();
    setupScraperForDomain($stack);

    $factory = mockFactory(new ScrapeResponse(results: makeResults(10), totalResults: 50));

    $job = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job->handle($factory);

    expect(DomainIndexResult::where('domain_id', $stack['domain']->id)->count())->toBe(10);
    // Verify upsert fields
    $first = DomainIndexResult::where('domain_id', $stack['domain']->id)->first();
    expect($first->first_seen_at->toDateString())->toBe(now()->toDateString());
    expect($first->last_seen_at->toDateString())->toBe(now()->toDateString());
});

test('upsert does not create duplicates on re-run', function () {
    Bus::fake([FetchIndexPageJob::class]);
    $stack = createFullStack();
    setupScraperForDomain($stack);

    $factory = mockFactory(new ScrapeResponse(results: makeResults(3), totalResults: 3));

    // First run
    $job = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job->handle($factory);

    // Second run — same URLs
    $job2 = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job2->handle($factory);

    // Should still be 3, not 6
    expect(DomainIndexResult::where('domain_id', $stack['domain']->id)->count())->toBe(3);
});

test('skips batch when total found is zero', function () {
    Bus::fake([FetchIndexPageJob::class]);
    $stack = createFullStack();
    setupScraperForDomain($stack);

    $factory = mockFactory(new ScrapeResponse(results: [], totalResults: 0));

    $job = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job->handle($factory);

    Bus::assertNothingBatched();
    expect($stack['domain']->fresh()->indexed_pages_count)->toBe(0);
});

test('skips when batch already running', function () {
    $stack = createFullStack();
    setupScraperForDomain($stack);
    $stack['domain']->update(['index_batch_id' => 'fake-batch-id']);

    // Mock Bus::findBatch to return active batch
    Bus::shouldReceive('findBatch')->with('fake-batch-id')->andReturn(
        (object) ['finished' => fn () => false]
    );

    $factory = Mockery::mock(ScraperFactory::class);
    $factory->shouldNotReceive('make');

    $job = new IndexDomainJob(domainId: $stack['domain']->id, engine: 'google', limit: 100);
    $job->handle($factory);
});

test('is on indexing queue', function () {
    $job = new IndexDomainJob(domainId: 1, engine: 'google', limit: 100);

    expect($job->queue)->toBe('indexing');
});
