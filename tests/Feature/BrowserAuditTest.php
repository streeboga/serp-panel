<?php

declare(strict_types=1);

use App\Jobs\BrowserAuditJob;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\Audit\BrowserAudit;
use App\Services\Audit\BrowserFindings;
use Illuminate\Support\Facades\Http;

covers(BrowserAudit::class, BrowserFindings::class, BrowserAuditJob::class);

beforeEach(function () {
    config([
        'audit.browser.enabled' => true,
        'audit.browser.url' => 'http://browser.test:8081',
        'audit.browser.token' => 'secret',
    ]);
});

function measurement(array $overrides = []): array
{
    return array_replace_recursive([
        'url' => 'https://test.com/',
        'status' => 200,
        'cls' => ['value' => 0.31, 'sources' => [['element' => 'img.hero', 'shift' => 0.29]]],
        'paint' => ['lcp' => 4300, 'fcp' => 900, 'lcp_element' => 'h1.title'],
        'timing' => ['ttfb' => 120, 'load' => 2100],
        'contrast' => [
            'checked' => 40,
            'unchecked' => 3,
            'unchecked_reasons' => ['фон картинкой или градиентом' => 3],
            'violations' => [['selector' => 'p.muted', 'ratio' => 2.85, 'required' => 4.5, 'text' => 'Серый текст']],
        ],
        'small_text' => [['selector' => 'span.note', 'font_size' => 10]],
    ], $overrides);
}

function resultFor(): PageAuditResult
{
    $h = createFullStack();

    $audit = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => 'running',
    ]);

    return PageAuditResult::create([
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/',
        'url_hash' => sha1('https://test.com/'),
        'path' => '/',
        'http_status' => 200,
        'score' => 100,
        'findings' => [],
        'metrics' => ['title' => 'Заголовок'],
    ]);
}

test('замеры браузера превращаются в находки', function () {
    $findings = (new BrowserFindings)->from(measurement());
    $codes = array_map(fn ($f) => $f->code, $findings);

    expect($codes)->toContain('browser.cls', 'browser.lcp', 'browser.contrast', 'browser.small_text');

    $cls = collect($findings)->firstWhere('code', 'browser.cls');
    // CLS 0.31 выше критического порога, и виновник должен приехать вместе с числом.
    expect($cls->severity->value)->toBe('critical')
        ->and($cls->value['виновники'][0]['element'])->toBe('img.hero');

    $lcp = collect($findings)->firstWhere('code', 'browser.lcp');
    expect($lcp->severity->value)->toBe('critical')
        ->and($lcp->value['элемент'])->toBe('h1.title');
});

test('хорошая страница не даёт браузерных находок', function () {
    $good = measurement([
        'cls' => ['value' => 0.01, 'sources' => []],
        'paint' => ['lcp' => 1200],
        'contrast' => ['violations' => []],
        'small_text' => [],
    ]);
    $good['contrast']['violations'] = [];
    $good['small_text'] = [];

    expect((new BrowserFindings)->from($good))->toBe([]);
});

test('находки доливаются в результат страницы, не затирая прежние', function () {
    Http::fake(['browser.test:8081/*' => Http::response(measurement(), 200)]);

    $result = resultFor();
    $result->update(['findings' => [[
        'check' => 'meta.title', 'code' => 'meta.title.long', 'category' => 'meta',
        'severity' => 'warning', 'message' => 'Длинный title', 'value' => 80, 'expected' => '10–70',
    ]]]);

    (new BrowserAuditJob($result->id, $result->url))->handle(app(BrowserAudit::class), new BrowserFindings);

    $fresh = $result->refresh();
    $codes = array_column($fresh->findings, 'code');

    expect($codes)->toContain('meta.title.long')
        ->and($codes)->toContain('browser.cls')
        ->and($fresh->metrics['title'])->toBe('Заголовок')
        ->and($fresh->metrics['browser']['contrast']['unchecked_reasons'])
        ->toBe(['фон картинкой или градиентом' => 3]);

    // Счётчики пересчитаны с учётом обеих групп.
    expect($fresh->issues_critical)->toBe(2)->and($fresh->score)->toBeLessThan(100);
});

test('повторный замер не плодит дубли находок', function () {
    Http::fake(['browser.test:8081/*' => Http::response(measurement(), 200)]);

    $result = resultFor();

    foreach ([1, 2] as $ignored) {
        (new BrowserAuditJob($result->id, $result->url))->handle(app(BrowserAudit::class), new BrowserFindings);
    }

    $codes = array_column($result->refresh()->findings, 'code');

    expect(array_count_values($codes)['browser.cls'])->toBe(1);
});

test('недоступный сервис оставляет страницу непроверенной, а не чистой', function () {
    Http::fake(['browser.test:8081/*' => Http::response('', 503)]);

    $result = resultFor();
    $result->update(['findings' => [], 'score' => 100]);

    (new BrowserAuditJob($result->id, $result->url))->handle(app(BrowserAudit::class), new BrowserFindings);

    $fresh = $result->refresh();

    // Ни находок, ни отметки о браузерных метриках — «не проверено».
    expect($fresh->findings)->toBe([])
        ->and($fresh->metrics)->not->toHaveKey('browser');
});

test('выключённый браузер ничего не запрашивает', function () {
    config(['audit.browser.enabled' => false]);
    Http::fake();

    expect(app(BrowserAudit::class)->measure('https://test.com/'))->toBeNull();

    Http::assertNothingSent();
});
