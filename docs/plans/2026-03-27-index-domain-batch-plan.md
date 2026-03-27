# IndexDomainJob Batch Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Переписать IndexDomainJob на батч-архитектуру с параллельным сбором страниц, отдельной очередью и rate limiting.

**Architecture:** Оркестратор (IndexDomainJob) делает первый запрос, парсит `<found>`, диспатчит батч из FetchIndexPageJob. Отдельная очередь `indexing` с max 3 воркерами. Общий rate limiter `xmlriver` для всех джобов, обращающихся к XMLRiver API.

**Tech Stack:** Laravel 13, Bus::batch(), RateLimitedWithRedis, Cache::lock(), Horizon supervisors, PostgreSQL

---

### Task 1: Миграция — добавить index_batch_id в domains

**Files:**
- Create: `database/migrations/2026_03_27_200000_add_index_batch_id_to_domains.php`
- Modify: `app/Models/Domain.php:36-39`

**Step 1: Создать миграцию**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('index_batch_id')->nullable()->after('indexed_pages_count');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('index_batch_id');
        });
    }
};
```

**Step 2: Обновить модель Domain**

В `app/Models/Domain.php:36-39` добавить `index_batch_id` в `$fillable`:

```php
protected $fillable = [
    'project_id', 'name', 'is_own',
    'type', 'parent_id', 'indexed_pages_count',
    'index_batch_id',
];
```

**Step 3: Запустить миграцию**

Run: `php artisan migrate`
Expected: Migrated successfully

**Step 4: Commit**

```bash
git add database/migrations/2026_03_27_200000_add_index_batch_id_to_domains.php app/Models/Domain.php
git commit -m "feat: add index_batch_id column to domains table"
```

---

### Task 2: Метод scrapePage() в XmlRiverAdapter

**Files:**
- Modify: `app/Services/Scrapers/Contracts/SerpScraperAdapter.php`
- Modify: `app/Services/Scrapers/Adapters/XmlRiverAdapter.php`
- Modify: `app/Services/Scrapers/Adapters/YandexXmlAdapter.php` (заглушка)
- Test: `tests/Unit/Services/Scrapers/XmlRiverAdapterTest.php`

**Step 1: Написать тест**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scrapers;

use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class XmlRiverAdapterTest extends TestCase
{
    private function makeXmlResponse(int $totalFound, int $resultsCount, int $startPosition = 1): string
    {
        $groups = '';
        for ($i = 0; $i < $resultsCount; $i++) {
            $pos = $startPosition + $i;
            $groups .= <<<XML
                <group>
                    <doc>
                        <url>https://example.com/page-{$pos}</url>
                        <title>Page {$pos}</title>
                        <passages><passage>Description {$pos}</passage></passages>
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
                        <grouping>{$groups}</grouping>
                    </results>
                </response>
            </yandexsearch>
        XML;
    }

    public function test_scrape_page_returns_single_page_results(): void
    {
        Http::fake([
            'xmlriver.test/*' => Http::response($this->makeXmlResponse(350, 10)),
        ]);

        $adapter = new XmlRiverAdapter('https://xmlriver.test', ['user' => 'u', 'key' => 'k']);
        $request = new ScrapeRequest(keyword: 'site:example.com', engine: 'google', device: 'desktop', regionId: 0);

        $response = $adapter->scrapePage($request, 1);

        $this->assertCount(10, $response->results);
        $this->assertEquals(350, $response->totalResults);
        $this->assertEquals(1, $response->results[0]->position);
        $this->assertEquals(10, $response->results[9]->position);
    }

    public function test_scrape_page_calculates_position_offset(): void
    {
        Http::fake([
            'xmlriver.test/*' => Http::response($this->makeXmlResponse(350, 10, 1)),
        ]);

        $adapter = new XmlRiverAdapter('https://xmlriver.test', ['user' => 'u', 'key' => 'k']);
        $request = new ScrapeRequest(keyword: 'site:example.com', engine: 'google', device: 'desktop', regionId: 0);

        $response = $adapter->scrapePage($request, 5);

        // Page 5 for google: positionOffset = (5 - 1) * 10 = 40
        $this->assertEquals(41, $response->results[0]->position);
    }

    public function test_scrape_page_returns_zero_total_on_empty_response(): void
    {
        $xml = '<yandexsearch><response><found priority="all">0</found><results><grouping></grouping></results></response></yandexsearch>';
        Http::fake([
            'xmlriver.test/*' => Http::response($xml),
        ]);

        $adapter = new XmlRiverAdapter('https://xmlriver.test', ['user' => 'u', 'key' => 'k']);
        $request = new ScrapeRequest(keyword: 'site:example.com', engine: 'google', device: 'desktop', regionId: 0);

        $response = $adapter->scrapePage($request, 1);

        $this->assertCount(0, $response->results);
        $this->assertEquals(0, $response->totalResults);
    }

    public function test_scrape_returns_total_found_from_xml(): void
    {
        Http::fake([
            'xmlriver.test/*' => Http::sequence()
                ->push($this->makeXmlResponse(350, 10))
                ->push($this->makeXmlResponse(350, 5)),
        ]);

        $adapter = new XmlRiverAdapter('https://xmlriver.test', ['user' => 'u', 'key' => 'k']);
        $request = new ScrapeRequest(keyword: 'site:example.com', engine: 'google', device: 'desktop', regionId: 0, limit: 30);

        $response = $adapter->scrape($request);

        $this->assertEquals(350, $response->totalResults);
        $this->assertCount(15, $response->results);
    }
}
```

**Step 2: Запустить тест, убедиться что падает**

Run: `cd /Users/k.mazurov/.cline/worktrees/b0182/serp-panel && php artisan test --filter=XmlRiverAdapterTest`
Expected: FAIL — method `scrapePage` does not exist

**Step 3: Добавить scrapePage в интерфейс**

В `app/Services/Scrapers/Contracts/SerpScraperAdapter.php` добавить метод:

```php
interface SerpScraperAdapter
{
    public function scrape(ScrapeRequest $request): ScrapeResponse;

    public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse;

    /** @return string[] */
    public function supportedEngines(): array;

    public function healthCheck(): bool;
}
```

**Step 4: Реализовать в XmlRiverAdapter**

Добавить метод `scrapePage()` и вспомогательный `parseTotalFound()`. Обновить `scrape()` чтобы возвращал `totalResults` из `<found>`.

В `app/Services/Scrapers/Adapters/XmlRiverAdapter.php`:

Заменить метод `scrape()` — добавить парсинг `totalFound` из первой страницы:

```php
public function scrape(ScrapeRequest $request): ScrapeResponse
{
    $url = $this->buildUrl();
    $baseParams = $this->buildParams($request);

    $firstPage = $request->engine === 'yandex' ? 0 : 1;
    $allResults = [];
    $page = $firstPage;
    $maxResults = $request->limit;
    $totalFound = 0;

    while (count($allResults) < $maxResults) {
        try {
            $response = Http::timeout(60)->get($url, array_merge($baseParams, ['page' => $page]));
            $body = $response->body();

            if ($page === $firstPage) {
                $totalFound = $this->parseTotalFound($body);
            }

            $pageResults = $this->parseXmlResponse($body, count($allResults));

            if (empty($pageResults)) {
                break;
            }

            $allResults = array_merge($allResults, $pageResults);

            if (count($pageResults) < self::RESULTS_PER_PAGE) {
                break;
            }

            $page++;
        } catch (\Exception $e) {
            Log::warning('XMLRiver page fetch failed', [
                'page' => $page,
                'error' => $e->getMessage(),
            ]);
            break;
        }
    }

    return new ScrapeResponse(
        results: $allResults,
        totalResults: $totalFound ?: count($allResults),
        rawResponse: '',
    );
}

public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse
{
    $url = $this->buildUrl();
    $baseParams = $this->buildParams($request);
    $firstPage = $request->engine === 'yandex' ? 0 : 1;
    $positionOffset = ($page - $firstPage) * self::RESULTS_PER_PAGE;

    try {
        $response = Http::timeout(60)->get($url, array_merge($baseParams, ['page' => $page]));
        $body = $response->body();
        $totalFound = $this->parseTotalFound($body);
        $results = $this->parseXmlResponse($body, $positionOffset);

        return new ScrapeResponse(
            results: $results,
            totalResults: $totalFound,
            rawResponse: '',
        );
    } catch (\Exception $e) {
        Log::warning('XMLRiver scrapePage failed', [
            'page' => $page,
            'error' => $e->getMessage(),
        ]);

        return new ScrapeResponse(results: [], totalResults: 0, rawResponse: '');
    }
}

private function buildUrl(): string
{
    $url = rtrim($this->baseUrl, '/');
    if (! str_contains($url, '/search')) {
        $url .= '/search/xml';
    }

    return $url;
}

/** @return array<string, mixed> */
private function buildParams(ScrapeRequest $request): array
{
    $params = [
        'user' => $this->credentials['user'] ?? '',
        'key' => $this->credentials['key'] ?? '',
        'query' => $request->keyword,
        'groupby' => self::RESULTS_PER_PAGE,
    ];

    if ($request->engine === 'yandex') {
        $params['lr'] = $request->yandexLr;
    } else {
        $params['gl'] = $request->googleGl;
        $params['hl'] = $request->googleHl;
    }

    return $params;
}

private function parseTotalFound(string $xml): int
{
    try {
        $doc = new \SimpleXMLElement($xml);
        foreach ($doc->response->found as $found) {
            if ((string) $found['priority'] === 'all') {
                return (int) $found;
            }
        }
    } catch (\Exception) {
    }

    return 0;
}
```

**Step 5: Добавить заглушку scrapePage в YandexXmlAdapter и WebhookAdapter**

В обоих адаптерах добавить:

```php
public function scrapePage(ScrapeRequest $request, int $page): ScrapeResponse
{
    return new ScrapeResponse(results: [], totalResults: 0, rawResponse: '');
}
```

**Step 6: Запустить тесты**

Run: `php artisan test --filter=XmlRiverAdapterTest`
Expected: 4 tests PASS

**Step 7: Commit**

```bash
git add app/Services/Scrapers/ tests/Unit/Services/Scrapers/XmlRiverAdapterTest.php
git commit -m "feat: add scrapePage() method and parseTotalFound() to XmlRiverAdapter"
```

---

### Task 3: Rate Limiter — xmlriver

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:27-37`

**Step 1: Добавить rate limiter**

В `app/Providers/AppServiceProvider.php` метод `boot()`, после существующих rate limiters:

```php
RateLimiter::for('xmlriver', function () {
    return Limit::perSecond(10);
});
```

**Step 2: Добавить middleware в ScrapeSerpJob**

В `app/Jobs/ScrapeSerpJob.php` добавить:

```php
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

// В классе:
public function middleware(): array
{
    return [new RateLimitedWithRedis('xmlriver')];
}
```

**Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Jobs/ScrapeSerpJob.php
git commit -m "feat: add xmlriver rate limiter, apply to ScrapeSerpJob"
```

---

### Task 4: Horizon — отдельная очередь indexing

**Files:**
- Modify: `config/horizon.php:215-265`

**Step 1: Обновить production supervisor**

В `config/horizon.php`, секция `environments.production`:

Изменить `serp-supervisor.maxProcesses` с `10` на `7`.

Добавить новый supervisor после `serp-supervisor`:

```php
'index-supervisor' => [
    'connection' => 'redis',
    'queue' => ['indexing'],
    'balance' => 'auto',
    'minProcesses' => 1,
    'maxProcesses' => 3,
    'tries' => 3,
    'timeout' => 120,
],
```

**Step 2: Обновить local supervisor**

В секции `environments.local.default-supervisor.queue` добавить `'indexing'`:

```php
'queue' => ['serp-scrape', 'wordstat', 'classification', 'indexing', 'default'],
```

**Step 3: Commit**

```bash
git add config/horizon.php
git commit -m "feat: add indexing queue with dedicated Horizon supervisor"
```

---

### Task 5: FetchIndexPageJob

**Files:**
- Create: `app/Jobs/FetchIndexPageJob.php`
- Test: `tests/Unit/Jobs/FetchIndexPageJobTest.php`

**Step 1: Написать тест**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\FetchIndexPageJob;
use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Tests\TestCase;

final class FetchIndexPageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_page_and_inserts_results(): void
    {
        $org = Organization::factory()->create();
        $project = Project::factory()->create(['organization_id' => $org->id]);
        $domain = Domain::factory()->create(['project_id' => $project->id, 'name' => 'example.com']);
        $scraper = Scraper::factory()->create([
            'organization_id' => $org->id,
            'type' => 'xmlriver',
            'is_active' => true,
            'supported_engines' => ['google'],
        ]);

        $results = [
            new SerpResultItem(11, 'https://example.com/p11', 'example.com', 'P11', 'Desc', 'organic', false),
            new SerpResultItem(12, 'https://example.com/p12', 'example.com', 'P12', 'Desc', 'organic', false),
        ];

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: $results, totalResults: 350));

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $job = new FetchIndexPageJob(
            domainId: $domain->id,
            engine: 'google',
            page: 2,
            collectedAt: now()->toDateString(),
        );

        $job->handle($mockFactory);

        $this->assertEquals(2, DomainIndexResult::where('domain_id', $domain->id)->count());
        $this->assertDatabaseHas('domain_index_results', [
            'domain_id' => $domain->id,
            'url' => 'https://example.com/p11',
            'position' => 11,
            'engine' => 'google',
        ]);
    }

    public function test_has_rate_limited_middleware(): void
    {
        $job = new FetchIndexPageJob(domainId: 1, engine: 'google', page: 1, collectedAt: '2026-03-27');
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    public function test_is_on_indexing_queue(): void
    {
        $job = new FetchIndexPageJob(domainId: 1, engine: 'google', page: 1, collectedAt: '2026-03-27');

        $this->assertEquals('indexing', $job->queue);
    }
}
```

**Step 2: Запустить тест, убедиться что падает**

Run: `php artisan test --filter=FetchIndexPageJobTest`
Expected: FAIL — class not found

**Step 3: Реализовать FetchIndexPageJob**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Scraper;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class FetchIndexPageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    public int $timeout = 90;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine,
        public readonly int $page,
        public readonly string $collectedAt,
    ) {
        $this->onQueue('indexing');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('xmlriver')];
    }

    public function handle(ScraperFactory $scraperFactory): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $domain = Domain::with('project')->findOrFail($this->domainId);
        $organizationId = $domain->project->organization_id;

        $scraper = Scraper::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->first(fn (Scraper $s) => in_array($this->engine, $s->supported_engines ?? [], true));

        if (! $scraper) {
            Log::warning("FetchIndexPageJob: no active scraper for engine [{$this->engine}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $this->engine,
            device: 'desktop',
            regionId: 0,
        );

        $response = $adapter->scrapePage($request, $this->page);

        foreach ($response->results as $item) {
            DomainIndexResult::create([
                'domain_id' => $domain->id,
                'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
                'title' => $item->title ? mb_convert_encoding($item->title, 'UTF-8', 'UTF-8') : null,
                'description' => $item->description ? mb_convert_encoding($item->description, 'UTF-8', 'UTF-8') : null,
                'snippet_links' => null,
                'position' => $item->position,
                'engine' => $this->engine,
                'collected_at' => $this->collectedAt,
            ]);
        }
    }
}
```

**Step 4: Запустить тесты**

Run: `php artisan test --filter=FetchIndexPageJobTest`
Expected: 3 tests PASS

**Step 5: Commit**

```bash
git add app/Jobs/FetchIndexPageJob.php tests/Unit/Jobs/FetchIndexPageJobTest.php
git commit -m "feat: add FetchIndexPageJob with batching and rate limiting"
```

---

### Task 6: Переписать IndexDomainJob (оркестратор)

**Files:**
- Modify: `app/Jobs/IndexDomainJob.php`
- Test: `tests/Unit/Jobs/IndexDomainJobTest.php`

**Step 1: Написать тест**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\FetchIndexPageJob;
use App\Jobs\IndexDomainJob;
use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class IndexDomainJobTest extends TestCase
{
    use RefreshDatabase;

    private Domain $domain;
    private Scraper $scraper;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $project = Project::factory()->create(['organization_id' => $org->id]);
        $this->domain = Domain::factory()->create(['project_id' => $project->id, 'name' => 'example.com']);
        $this->scraper = Scraper::factory()->create([
            'organization_id' => $org->id,
            'type' => 'xmlriver',
            'is_active' => true,
            'supported_engines' => ['google'],
        ]);
    }

    public function test_dispatches_batch_for_remaining_pages(): void
    {
        Bus::fake([FetchIndexPageJob::class]);

        $results = array_map(
            fn ($i) => new SerpResultItem($i, "https://example.com/p{$i}", 'example.com', "P{$i}", 'Desc', 'organic', false),
            range(1, 10),
        );

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: $results, totalResults: 350));

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $job = new IndexDomainJob(domainId: $this->domain->id, engine: 'google', limit: 100);
        $job->handle($mockFactory);

        // 350 total, limit 100 → 10 pages needed, page 1 done by orchestrator → 9 batch jobs
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 9
            && $batch->jobs->every(fn ($job) => $job instanceof FetchIndexPageJob)
        );
    }

    public function test_saves_first_page_results(): void
    {
        Bus::fake([FetchIndexPageJob::class]);

        $results = array_map(
            fn ($i) => new SerpResultItem($i, "https://example.com/p{$i}", 'example.com', "P{$i}", 'Desc', 'organic', false),
            range(1, 10),
        );

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: $results, totalResults: 50));

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $job = new IndexDomainJob(domainId: $this->domain->id, engine: 'google', limit: 100);
        $job->handle($mockFactory);

        $this->assertEquals(10, DomainIndexResult::where('domain_id', $this->domain->id)->count());
    }

    public function test_skips_batch_when_total_found_is_zero(): void
    {
        Bus::fake([FetchIndexPageJob::class]);

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: [], totalResults: 0));

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $job = new IndexDomainJob(domainId: $this->domain->id, engine: 'google', limit: 100);
        $job->handle($mockFactory);

        Bus::assertNothingBatched();
        $this->assertEquals(0, $this->domain->fresh()->indexed_pages_count);
    }

    public function test_skips_when_lock_is_held(): void
    {
        $lock = Cache::lock("index-domain:{$this->domain->id}:google", 600);
        $lock->get();

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->expects($this->never())->method('make');

        $job = new IndexDomainJob(domainId: $this->domain->id, engine: 'google', limit: 100);

        // Job should release back to queue — simulate by calling handle
        // With lock held, it should return early
        $job->handle($mockFactory);

        $lock->release();
    }

    public function test_deletes_old_results_before_saving(): void
    {
        Bus::fake([FetchIndexPageJob::class]);

        // Pre-existing result for today
        DomainIndexResult::create([
            'domain_id' => $this->domain->id,
            'url' => 'https://example.com/old',
            'position' => 1,
            'engine' => 'google',
            'collected_at' => now()->toDateString(),
        ]);

        $results = [new SerpResultItem(1, 'https://example.com/new', 'example.com', 'New', 'Desc', 'organic', false)];

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: $results, totalResults: 5));

        $mockFactory = $this->createMock(ScraperFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $job = new IndexDomainJob(domainId: $this->domain->id, engine: 'google', limit: 100);
        $job->handle($mockFactory);

        $this->assertDatabaseMissing('domain_index_results', ['url' => 'https://example.com/old']);
        $this->assertDatabaseHas('domain_index_results', ['url' => 'https://example.com/new']);
    }

    public function test_is_on_indexing_queue(): void
    {
        $job = new IndexDomainJob(domainId: 1, engine: 'google', limit: 100);

        $this->assertEquals('indexing', $job->queue);
    }
}
```

**Step 2: Запустить тесты, убедиться что падают**

Run: `php artisan test --filter=IndexDomainJobTest`
Expected: FAIL

**Step 3: Переписать IndexDomainJob**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Scraper;
use App\Services\Scrapers\DTO\ScrapeRequest;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class IndexDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public function __construct(
        public readonly int $domainId,
        public readonly string $engine = 'google',
        public readonly int $limit = 100,
    ) {
        $this->onQueue('indexing');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('xmlriver')];
    }

    public function handle(ScraperFactory $scraperFactory): void
    {
        $lock = Cache::lock("index-domain:{$this->domainId}:{$this->engine}", 600);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $this->process($scraperFactory);
        } finally {
            $lock->release();
        }
    }

    private function process(ScraperFactory $scraperFactory): void
    {
        $domain = Domain::with('project')->findOrFail($this->domainId);
        $organizationId = $domain->project->organization_id;

        $scraper = Scraper::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->first(fn (Scraper $s) => in_array($this->engine, $s->supported_engines ?? [], true));

        if (! $scraper) {
            Log::warning("IndexDomainJob: no active scraper for engine [{$this->engine}] in org [{$organizationId}]");

            return;
        }

        $adapter = $scraperFactory->make($scraper);
        $firstPage = $this->engine === 'yandex' ? 0 : 1;

        $request = new ScrapeRequest(
            keyword: "site:{$domain->name}",
            engine: $this->engine,
            device: 'desktop',
            regionId: 0,
            limit: $this->limit,
        );

        // Fetch first page to get totalFound
        $firstResponse = $adapter->scrapePage($request, $firstPage);
        $totalFound = $firstResponse->totalResults;
        $today = now()->toDateString();

        // Delete old results for this engine + date
        $domain->indexResults()
            ->where('engine', $this->engine)
            ->where('collected_at', $today)
            ->delete();

        // Save first page results
        foreach ($firstResponse->results as $item) {
            DomainIndexResult::create([
                'domain_id' => $domain->id,
                'url' => mb_convert_encoding($item->url, 'UTF-8', 'UTF-8'),
                'title' => $item->title ? mb_convert_encoding($item->title, 'UTF-8', 'UTF-8') : null,
                'description' => $item->description ? mb_convert_encoding($item->description, 'UTF-8', 'UTF-8') : null,
                'snippet_links' => null,
                'position' => $item->position,
                'engine' => $this->engine,
                'collected_at' => $today,
            ]);
        }

        if ($totalFound === 0 || empty($firstResponse->results)) {
            $domain->update(['indexed_pages_count' => 0, 'index_batch_id' => null]);
            Log::info("IndexDomainJob: {$domain->name} — not in index");

            return;
        }

        // Calculate remaining pages
        $maxPages = min(
            (int) ceil($totalFound / 10),
            (int) ceil($this->limit / 10),
            100, // Google hard limit
        );

        if ($maxPages <= 1) {
            // Only one page — no batch needed
            $domain->update([
                'indexed_pages_count' => count($firstResponse->results),
                'index_batch_id' => null,
            ]);
            Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, " . count($firstResponse->results) . ' collected (single page)');

            return;
        }

        // Dispatch batch for remaining pages
        $jobs = [];
        for ($page = $firstPage + 1; $page < $firstPage + $maxPages; $page++) {
            $jobs[] = new FetchIndexPageJob(
                domainId: $domain->id,
                engine: $this->engine,
                page: $page,
                collectedAt: $today,
            );
        }

        $batch = Bus::batch($jobs)
            ->name("index:{$domain->name}:{$this->engine}")
            ->onQueue('indexing')
            ->allowFailures()
            ->finally(function () use ($domain, $today) {
                $count = $domain->indexResults()
                    ->where('engine', $this->engine)
                    ->where('collected_at', $today)
                    ->count();

                $domain->update([
                    'indexed_pages_count' => $count,
                    'index_batch_id' => null,
                ]);

                Log::info("IndexDomainJob batch complete: {$domain->name} — {$count} pages collected");
            })
            ->dispatch();

        $domain->update(['index_batch_id' => $batch->id]);

        if ($totalFound > 1000) {
            Log::warning("IndexDomainJob: {$domain->name} has {$totalFound} indexed pages, truncated to max 1000");
        }

        Log::info("IndexDomainJob: {$domain->name} — {$totalFound} found, dispatched " . count($jobs) . ' page jobs');
    }
}
```

**Step 4: Запустить тесты**

Run: `php artisan test --filter=IndexDomainJobTest`
Expected: 6 tests PASS

**Step 5: Commit**

```bash
git add app/Jobs/IndexDomainJob.php tests/Unit/Jobs/IndexDomainJobTest.php
git commit -m "feat: rewrite IndexDomainJob as batch orchestrator with lock and pagination"
```

---

### Task 7: API — index-status и cancel endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/V1/DomainController.php:133-145`
- Modify: `routes/api.php`

**Step 1: Добавить indexStatus метод в DomainController**

После метода `indexDomain()` в `app/Http/Controllers/Api/V1/DomainController.php`:

```php
/**
 * Статус индексации домена
 *
 * Возвращает текущий прогресс индексации.
 */
#[PathParameter('domain', description: 'ID домена', example: '1')]
#[Response(200, description: 'Статус индексации')]
public function indexStatus(Request $request, Domain $domain): JsonResponse
{
    if ($domain->project->organization_id !== $request->get('organization')->id) {
        abort(404);
    }

    if (! $domain->index_batch_id) {
        return response()->json([
            'data' => [
                'status' => 'idle',
                'total_found' => $domain->indexed_pages_count,
                'collected' => $domain->indexed_pages_count ?? 0,
                'progress' => 100,
                'batch_id' => null,
            ],
        ]);
    }

    $batch = Bus::findBatch($domain->index_batch_id);

    if (! $batch) {
        return response()->json([
            'data' => [
                'status' => 'idle',
                'total_found' => $domain->indexed_pages_count,
                'collected' => $domain->indexed_pages_count ?? 0,
                'progress' => 100,
                'batch_id' => null,
            ],
        ]);
    }

    $status = match (true) {
        $batch->cancelled() => 'cancelled',
        $batch->hasFailures() && $batch->finished() => 'failed',
        $batch->finished() => 'completed',
        default => 'indexing',
    };

    return response()->json([
        'data' => [
            'status' => $status,
            'total_found' => $domain->indexed_pages_count,
            'collected' => $domain->indexResults()
                ->where('engine', 'google')
                ->where('collected_at', now()->toDateString())
                ->count(),
            'progress' => $batch->progress(),
            'batch_id' => $batch->id,
        ],
    ]);
}

/**
 * Отмена индексации домена
 *
 * Отменяет текущий батч индексации.
 */
#[PathParameter('domain', description: 'ID домена', example: '1')]
#[Response(200, description: 'Индексация отменена')]
public function cancelIndex(Request $request, Domain $domain): JsonResponse
{
    if ($domain->project->organization_id !== $request->get('organization')->id) {
        abort(404);
    }

    if ($domain->index_batch_id) {
        $batch = Bus::findBatch($domain->index_batch_id);
        $batch?->cancel();
        $domain->update(['index_batch_id' => null]);
    }

    return response()->json(['data' => ['message' => 'Индексация отменена']]);
}
```

Добавить `use Illuminate\Support\Facades\Bus;` в импорты контроллера.

**Step 2: Добавить роуты**

В `routes/api.php`, рядом с существующими domain-роутами:

```php
Route::get('domains/{domain}/index-status', [DomainController::class, 'indexStatus']);
```

В секции manager+:

```php
Route::delete('domains/{domain}/index', [DomainController::class, 'cancelIndex']);
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/V1/DomainController.php routes/api.php
git commit -m "feat: add index-status and cancel-index API endpoints"
```

---

### Task 8: Интеграционный тест полного цикла

**Files:**
- Test: `tests/Feature/Jobs/IndexDomainBatchTest.php`

**Step 1: Написать интеграционный тест**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\FetchIndexPageJob;
use App\Jobs\IndexDomainJob;
use App\Models\Domain;
use App\Models\DomainIndexResult;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Scraper;
use App\Services\Scrapers\Adapters\XmlRiverAdapter;
use App\Services\Scrapers\DTO\ScrapeResponse;
use App\Services\Scrapers\DTO\SerpResultItem;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class IndexDomainBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_batch_cycle_with_multiple_pages(): void
    {
        Bus::fake([FetchIndexPageJob::class]);

        $org = Organization::factory()->create();
        $project = Project::factory()->create(['organization_id' => $org->id]);
        $domain = Domain::factory()->create(['project_id' => $project->id, 'name' => 'test-site.com']);
        Scraper::factory()->create([
            'organization_id' => $org->id,
            'type' => 'xmlriver',
            'is_active' => true,
            'supported_engines' => ['google'],
        ]);

        $results = array_map(
            fn ($i) => new SerpResultItem($i, "https://test-site.com/p{$i}", 'test-site.com', "P{$i}", 'Desc', 'organic', false),
            range(1, 10),
        );

        $mockAdapter = $this->createMock(XmlRiverAdapter::class);
        $mockAdapter->method('scrapePage')
            ->willReturn(new ScrapeResponse(results: $results, totalResults: 250));

        $this->app->bind(ScraperFactory::class, function () use ($mockAdapter) {
            $factory = $this->createMock(ScraperFactory::class);
            $factory->method('make')->willReturn($mockAdapter);

            return $factory;
        });

        IndexDomainJob::dispatch($domain->id, 'google', 250);

        // First page saved by orchestrator
        $this->assertEquals(10, DomainIndexResult::where('domain_id', $domain->id)->count());

        // 250 results / 10 per page = 25 pages, page 1 done → 24 remaining
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 24);

        // Batch ID saved to domain
        $this->assertNotNull($domain->fresh()->index_batch_id);
    }
}
```

**Step 2: Запустить тест**

Run: `php artisan test --filter=IndexDomainBatchTest`
Expected: PASS

**Step 3: Запустить все тесты**

Run: `php artisan test`
Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/Feature/Jobs/IndexDomainBatchTest.php
git commit -m "test: add integration test for IndexDomainJob batch cycle"
```

---

### Task 9: Обновить CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Обновить секцию Queue Jobs**

Добавить очередь `indexing` и новые джобы:

```markdown
## Queue Jobs

- `serp-scrape`: ScrapeSerpJob — collects SERP via adapter
- `indexing`: IndexDomainJob (orchestrator) + FetchIndexPageJob (page worker) — domain index via site: query, batch processing
- `wordstat`: CollectWordstatJob — collects Wordstat frequencies
- `classification`: ClassifyDomainsJob — classifies domains from SERP
- `default`: SendPositionAlertJob — sends Telegram/Email alerts on position changes
- Run: `php artisan queue:work --queue=serp-scrape,indexing,wordstat,classification,default`
```

**Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md with indexing queue and batch jobs"
```

---

## Порядок выполнения

| Task | Зависит от | Описание |
|------|-----------|----------|
| 1 | — | Миграция index_batch_id |
| 2 | — | scrapePage() в XmlRiverAdapter |
| 3 | — | Rate limiter xmlriver |
| 4 | — | Horizon supervisor indexing |
| 5 | 2, 3 | FetchIndexPageJob |
| 6 | 1, 2, 3, 5 | IndexDomainJob (оркестратор) |
| 7 | 6 | API endpoints |
| 8 | 6 | Интеграционный тест |
| 9 | 6 | Документация |

Tasks 1-4 можно выполнять параллельно.

План сохранён. Два варианта реализации:

**1. Subagent-Driven (эта сессия)** — запускаю агентов на каждый таск, ревью между ними, быстрая итерация

**2. Параллельная сессия** — открываешь новую сессию с executing-plans, пакетное выполнение с чекпоинтами

Какой подход?