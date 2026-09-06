<?php

declare(strict_types=1);

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Contracts\Repositories\SiteAuditRepositoryInterface;
use App\Enums\AuditStatus;
use App\Http\Controllers\Api\V1\AuditController;
use App\Jobs\AuditPageJob;
use App\Jobs\FinalizeSiteAuditJob;
use App\Models\AuditResource;
use App\Models\Keyword;
use App\Models\Page;
use App\Models\SiteAudit;
use App\Services\Audit\SiteChecker;
use App\Services\SiteAuditService;
use Illuminate\Support\Facades\Http;

covers(AuditController::class, SiteAuditService::class);

beforeEach(function () {
    // Боевой лимит вежливости — 2 запроса в секунду. На sync-очереди отложенная
    // джоба не возвращается, поэтому в тестах поводок отпускаем.
    config(['audit.requests_per_second' => 10_000]);
});

function goodPage(string $title = 'Разработка сайтов на Laravel под ключ'): string
{
    return <<<HTML
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        <meta name="description" content="Делаем сайты и веб-приложения на Laravel: от прототипа до поддержки клиента.">
        <link rel="canonical" href="https://test.com/">
        <meta property="og:title" content="Разработка"><meta property="og:description" content="Сайты">
        <meta property="og:image" content="https://test.com/og.png"><meta property="og:url" content="https://test.com/">
        <meta property="og:type" content="website">
        <script type="application/ld+json">{"@type":"Organization"}</script>
    </head>
    <body><h1>Разработка сайтов</h1><p>Пишем на Laravel.</p><img src="/a.png" alt="Схема" width="10" height="10"></body>
    </html>
    HTML;
}

test('запускает аудит одного URL и складывает находки', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $response = $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'url', 'url' => 'https://test.com/page/'],
        orgHeaders($h['org']),
    );

    $response->assertStatus(201)->assertJsonPath('data.type', 'site-audits');

    $audit = SiteAudit::latest('id')->firstOrFail();

    expect($audit->status)->toBe(AuditStatus::Completed)
        ->and($audit->pages_total)->toBe(1)
        ->and($audit->score)->not->toBeNull();

    $this->assertDatabaseHas('page_audit_results', [
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/page/',
        'http_status' => 200,
    ]);
});

test('собирает список страниц из карты сайта', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url><loc>https://test.com/</loc></url>
        <url><loc>https://test.com/services/</loc></url>
        <url><loc>https://test.com/admin/secret</loc></url>
    </urlset>
    XML;

    Http::fake([
        'test.com/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\nSitemap: https://test.com/sitemap.xml", 200),
        'test.com/sitemap.xml' => Http::response($sitemap, 200, ['Content-Type' => 'application/xml']),
        '*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html']),
    ]);

    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site', 'domain_id' => $h['domain']->id],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $audit = SiteAudit::latest('id')->firstOrFail();

    // /admin/secret закрыт в robots.txt — в прогон попасть не должен.
    $urls = $audit->results()->pluck('url')->all();

    expect($urls)->toContain('https://test.com/')
        ->and($urls)->toContain('https://test.com/services/')
        ->and($urls)->not->toContain('https://test.com/admin/secret');
});

test('считает релевантность страницы её целевым ключам', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $page = Page::create([
        'project_id' => $h['project']->id,
        'domain_id' => $h['domain']->id,
        'url' => 'https://test.com/',
    ]);

    $keyword = Keyword::create([
        'cluster_id' => $h['cluster']->id,
        'keyword' => 'ремонт котлов',
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $page->keywords()->attach($keyword->id, ['is_target' => true]);

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'pages', 'page_ids' => [$page->id]],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $result = SiteAudit::latest('id')->firstOrFail()->results()->firstOrFail();

    expect(array_column($result->findings, 'code'))->toContain('content.relevance.text')
        ->and($result->metrics['relevance'][0]['keyword'])->toBe('ремонт котлов')
        ->and($result->page_id)->toBe($page->id);
});

test('отдаёт результаты прогона и фильтрует по severity', function () {
    Http::fake(['*' => Http::response('<html><body><p>ничего</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'url', 'url' => 'https://test.com/bad/'],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $audit = SiteAudit::latest('id')->firstOrFail();

    $this->actingAs($h['user'])
        ->getJson("/api/v1/audits/{$audit->id}/results?severity=critical", orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'page-audit-results');

    // Кириллицу в query-строке положено процентно кодировать — так её шлёт фронтенд.
    $search = rawurlencode('нетакого');

    $this->actingAs($h['user'])
        ->getJson("/api/v1/audits/{$audit->id}/results?severity=notice&search={$search}", orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('разовая проверка URL отвечает синхронно и ничего не пишет', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/audit/url', ['url' => 'https://test.com/'], orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonPath('data.http_status', 200)
        ->assertJsonPath('data.error', null);

    $this->assertDatabaseCount('page_audit_results', 0);
});

test('не запускает второй прогон поверх идущего', function () {
    $h = createFullStack();

    SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => AuditStatus::Running,
    ]);

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site'],
        orgHeaders($h['org']),
    )->assertStatus(409);
});

test('чужой прогон не виден', function () {
    $mine = createFullStack();
    $theirs = createFullStack();

    $audit = SiteAudit::create([
        'project_id' => $theirs['project']->id,
        'scope' => 'site',
        'status' => AuditStatus::Completed,
    ]);

    $this->actingAs($mine['user'])
        ->getJson("/api/v1/audits/{$audit->id}", orgHeaders($mine['org']))
        ->assertNotFound();
});

test('analyst не может запускать аудит', function () {
    $h = createFullStack('analyst');

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site'],
        orgHeaders($h['org']),
    )->assertForbidden();
});

test('проверки уровня сайта ловят отсутствие robots и битую карту', function () {
    Http::fake([
        'test.com/robots.txt' => Http::response('', 404),
        'test.com/sitemap.xml' => Http::response('', 404),
        '*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html']),
    ]);

    $checker = app(SiteChecker::class);
    $result = $checker->run('https://test.com');

    $codes = array_map(fn ($f) => $f->check, $result['findings']);

    expect($codes)->toContain('site.robots.missing')
        ->and($codes)->toContain('site.sitemap.missing')
        // Несуществующая страница отдала 200 вместо 404 — это ошибка сайта.
        ->and($codes)->toContain('site.not_found');
});

test('джоба страницы ограничена временем, а не числом попыток', function () {
    // Лимитер вежливости отпускает джобу обратно в очередь, и каждый отпуск съедает
    // попытку. С фиксированным $tries батч выкашивало целиком.
    $job = new AuditPageJob(auditId: 1, url: 'https://test.com/');

    expect($job->tries)->toBe(0)
        ->and($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addMinutes(30)->getTimestamp());
});

test('потерянные страницы не выдаются за успешный прогон', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $audit = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => AuditStatus::Running,
        'pages_total' => 10,
    ]);

    // Ни одна страница не записалась — батч отработал, результатов нет.
    (new FinalizeSiteAuditJob($audit->id))->handle(
        app(SiteAuditRepositoryInterface::class),
        app(PageAuditResultRepositoryInterface::class),
        app(AuditResourceRepositoryInterface::class),
    );

    expect($audit->refresh()->error)->toContain('10 из 10');
});

test('аудит сайта не уходит на чужие домены', function () {
    Http::fake([
        'test.com/robots.txt' => Http::response('', 404),
        'test.com/sitemap.xml' => Http::response('', 404),
        '*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html']),
    ]);

    $h = createFullStack();

    // Реестр Page держит и конкурентов — их пишет туда MatchPagesFromSerpListener.
    Page::create(['project_id' => $h['project']->id, 'url' => 'https://test.com/services/']);
    Page::create(['project_id' => $h['project']->id, 'url' => 'https://www.reddit.com/r/seo/comments/x/']);

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site', 'domain_id' => $h['domain']->id],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $urls = SiteAudit::latest('id')->firstOrFail()->results()->pluck('url')->all();

    expect($urls)->toContain('https://test.com/services/')
        ->and($urls)->not->toContain('https://www.reddit.com/r/seo/comments/x/');
});

test('явно выбранные страницы проверяются даже на чужом хосте', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();
    $page = Page::create(['project_id' => $h['project']->id, 'url' => 'https://competitor.example/x/']);

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'pages', 'page_ids' => [$page->id]],
        orgHeaders($h['org']),
    )->assertStatus(201);

    expect(SiteAudit::latest('id')->firstOrFail()->results()->pluck('url')->all())
        ->toContain('https://competitor.example/x/');
});

test('каталог проверок отдаётся по категориям', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/audit/checks', orgHeaders($h['org']))
        ->assertOk();

    $catalog = $response->json('data');
    $categories = array_column($catalog, 'category');

    expect($categories)->toContain('technical', 'meta', 'content', 'links', 'images');

    $codes = array_merge(...array_map(
        fn (array $row): array => array_column($row['checks'], 'code'),
        $catalog,
    ));

    // Число проверок зависит от установленных пакетов — привязываться к нему нельзя.
    expect($codes)->toContain('meta.title', 'content.relevance', 'images.alt')
        ->and($codes)->toEqual(array_unique($codes));

    // Каждая проверка обязана назвать себя — каталог показывается людям.
    foreach ($catalog as $row) {
        expect($row['title'])->not->toBeEmpty();

        foreach ($row['checks'] as $check) {
            expect($check['title'])->not->toBeEmpty();
        }
    }
});

test('прогон можно сузить до отдельных проверок', function () {
    Http::fake(['*' => Http::response('<html><body><p>ничего</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'url', 'url' => 'https://test.com/x/', 'check_codes' => ['meta.title']],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $result = SiteAudit::latest('id')->firstOrFail()->results()->firstOrFail();
    $checks = array_unique(array_column($result->findings, 'check'));

    // Ровно одна проверка — отключённые молчат, хотя дефекты на странице есть.
    expect($checks)->toBe(['meta.title']);
});

test('несуществующий код проверки отвергается', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site', 'check_codes' => ['meta.нетакой']],
        orgHeaders($h['org']),
    )->assertStatus(422)->assertJsonValidationErrors(['check_codes.0']);
});

test('ресурс проверяется один раз, сколько бы страниц на него ни ссылалось', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url><loc>https://test.com/a/</loc></url>
        <url><loc>https://test.com/b/</loc></url>
    </urlset>
    XML;

    // Обе страницы ссылаются на один и тот же битый адрес и одну и ту же картинку.
    $page = '<html lang="ru"><head><title>Страница про разработку сайтов</title>'
        .'<meta name="description" content="Описание страницы достаточной длины для того, чтобы пройти порог.">'
        .'</head><body><h1>Заголовок</h1>'
        .'<a href="/dead/">Битая</a><img src="/shared.png" alt="Общая" width="10" height="10">'
        .'</body></html>';

    Http::fake([
        'test.com/robots.txt' => Http::response('', 404),
        'test.com/sitemap.xml' => Http::response($sitemap, 200, ['Content-Type' => 'application/xml']),
        'test.com/dead/' => Http::response('', 404),
        'test.com/shared.png' => Http::response('', 200, ['Content-Length' => '400000', 'Content-Type' => 'image/png']),
        '*' => Http::response($page, 200, ['Content-Type' => 'text/html']),
    ]);

    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'site', 'domain_id' => $h['domain']->id],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $audit = SiteAudit::latest('id')->firstOrFail();

    // Две страницы ссылались на /dead/ — строка одна, счётчик два.
    $dead = AuditResource::where('site_audit_id', $audit->id)
        ->where('url', 'https://test.com/dead/')
        ->firstOrFail();

    expect($dead->reference_count)->toBe(2)
        ->and($dead->status)->toBe(404)
        ->and($dead->checked_at)->not->toBeNull();

    $codes = array_column($audit->findings, 'code');

    expect($codes)
        ->toContain('site.resources.broken')
        ->toContain('site.resources.heavy_images')
        // Обе страницы отдают один и тот же title — это видно только на сайте целиком.
        ->toContain('site.duplicate.title');

    expect($audit->metrics['resources']['broken'])->toBe(1);
});

test('этап ресурсов выключается конфигом', function () {
    config(['audit.check_resources' => false]);

    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'url', 'url' => 'https://test.com/x/'],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $audit = SiteAudit::latest('id')->firstOrFail();

    expect($audit->status->value)->toBe('completed')
        ->and(AuditResource::where('site_audit_id', $audit->id)->whereNotNull('checked_at')->count())->toBe(0);
});

test('политика заглушения проекта попадает в прогон и вычитается из счётчиков', function () {
    Http::fake(['*' => Http::response(goodPage(), 200, ['Content-Type' => 'text/html'])]);

    $h = createFullStack();

    $this->actingAs($h['user'])->patchJson(
        "/api/v1/projects/{$h['project']->id}/muted-codes",
        ['muted_codes' => ['http.analytics.missing' => 'счётчик подключается после ответа на cookie-баннер']],
        orgHeaders($h['org']),
    )->assertStatus(200);

    expect($h['project']->fresh()->muted_codes)
        ->toBe(['http.analytics.missing' => 'счётчик подключается после ответа на cookie-баннер']);

    $this->actingAs($h['user'])->postJson(
        "/api/v1/projects/{$h['project']->id}/audits",
        ['scope' => 'url', 'url' => 'https://test.com/muted/'],
        orgHeaders($h['org']),
    )->assertStatus(201);

    $audit = SiteAudit::latest('id')->firstOrFail();

    // Снимок политики лежит в прогоне: прогон должен читаться и после её правки.
    expect($audit->muted_codes)->toBe(['http.analytics.missing' => 'счётчик подключается после ответа на cookie-баннер']);

    $result = $audit->results()->firstOrFail();
    $muted = array_values(array_filter($result->findings, fn (array $f): bool => ($f['muted'] ?? false) === true));

    expect($muted)->not->toBeEmpty()
        ->and($muted[0]['code'])->toBe('http.analytics.missing')
        ->and($muted[0]['mute_reason'])->toContain('cookie-баннер')
        ->and($result->issues_muted)->toBe(count($muted));

    // Счётчик страницы считает только незаглушённое.
    $visible = array_values(array_filter($result->findings, fn (array $f): bool => ($f['muted'] ?? false) !== true));

    expect($result->issues_critical + $result->issues_warning + $result->issues_notice)->toBe(count($visible));

    // Сводка прогона складывается из страниц, поэтому заглушённое видно отдельно
    // и в неё не попадает. Финализатор идемпотентен, зовём его явно: батч в
    // тестовой очереди свой finally отдаёт не всегда.
    (new FinalizeSiteAuditJob($audit->id))->handle(
        app(SiteAuditRepositoryInterface::class),
        app(PageAuditResultRepositoryInterface::class),
        app(AuditResourceRepositoryInterface::class),
    );

    $audit->refresh();

    expect($audit->issues_muted)->toBe($result->issues_muted)
        ->and($audit->issues_critical + $audit->issues_warning + $audit->issues_notice)
        ->toBe($result->issues_critical + $result->issues_warning + $result->issues_notice);
});

test('код проверки политикой заглушения не принимается', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])->patchJson(
        "/api/v1/projects/{$h['project']->id}/muted-codes",
        ['muted_codes' => ['analytics' => 'односегментный код это код проверки, а не находки']],
        orgHeaders($h['org']),
    )->assertStatus(422);

    // Причина обязательна: заглушка без причины через месяц становится дырой.
    $this->actingAs($h['user'])->patchJson(
        "/api/v1/projects/{$h['project']->id}/muted-codes",
        ['muted_codes' => ['http.analytics.missing' => '']],
        orgHeaders($h['org']),
    )->assertStatus(422);
});
