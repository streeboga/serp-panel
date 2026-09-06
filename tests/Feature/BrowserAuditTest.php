<?php

declare(strict_types=1);

use App\Jobs\BrowserAuditJob;
use App\Jobs\LighthouseJob;
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
    $findings = (new BrowserFindings)->forViewport('mobile')->from(measurement());
    $codes = array_map(fn ($f) => $f->code, $findings);

    expect($codes)->toContain('browser.mobile.cls', 'browser.mobile.lcp', 'browser.mobile.contrast', 'browser.mobile.small_text');

    $cls = collect($findings)->firstWhere('code', 'browser.mobile.cls');
    // CLS 0.31 выше критического порога, и виновник должен приехать вместе с числом.
    expect($cls->severity->value)->toBe('critical')
        ->and($cls->value['виновники'][0]['element'])->toBe('img.hero');

    $lcp = collect($findings)->firstWhere('code', 'browser.mobile.lcp');
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

    expect((new BrowserFindings)->forViewport('mobile')->from($good))->toBe([]);
});

test('находки доливаются в результат страницы, не затирая прежние', function () {
    Http::fake(['browser.test:8081/*' => Http::response(measurement(), 200)]);

    $result = resultFor();
    $result->update(['findings' => [[
        'check' => 'meta.title', 'code' => 'meta.title.long', 'category' => 'meta',
        'severity' => 'warning', 'message' => 'Длинный title', 'value' => 80, 'expected' => '10–70',
    ]]]);

    runJob(new BrowserAuditJob($result->id, $result->url));

    $fresh = $result->refresh();
    $codes = array_column($fresh->findings, 'code');

    expect($codes)->toContain('meta.title.long')
        ->and($codes)->toContain('browser.mobile.cls')
        ->and($fresh->metrics['title'])->toBe('Заголовок')
        ->and($fresh->metrics['browser']['mobile']['contrast']['unchecked_reasons'])
        ->toBe(['фон картинкой или градиентом' => 3]);

    // Счётчики пересчитаны с учётом обеих групп.
    expect($fresh->issues_critical)->toBe(2)->and($fresh->score)->toBeLessThan(100);
});

test('повторный замер не плодит дубли находок', function () {
    Http::fake(['browser.test:8081/*' => Http::response(measurement(), 200)]);

    $result = resultFor();

    foreach ([1, 2] as $ignored) {
        runJob(new BrowserAuditJob($result->id, $result->url));
    }

    $codes = array_column($result->refresh()->findings, 'code');

    expect(array_count_values($codes)['browser.mobile.cls'])->toBe(1);
});

test('недоступный сервис оставляет страницу непроверенной, а не чистой', function () {
    Http::fake(['browser.test:8081/*' => Http::response('', 503)]);

    $result = resultFor();
    $result->update(['findings' => [], 'score' => 100]);

    runJob(new BrowserAuditJob($result->id, $result->url));

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

test('браузерный этап не теряет пометки заглушённых находок', function () {
    // Этап пересобирает находки из массивов, поэтому политику заглушения надо
    // применять заново. Пока он этого не делал, страницы, дошедшие до браузера,
    // приходили в прогон с удвоенным счётчиком: 20 из 234 на eq.team 06.09.2026.
    $result = resultFor();
    $result->audit->update(['muted_codes' => ['browser.mobile.contrast' => 'фон картинкой, замер недостоверен']]);

    $result->update(['findings' => [[
        'check' => 'content.nausea',
        'code' => 'content.nausea.academic',
        'category' => 'content',
        'severity' => 'notice',
        'message' => 'Академическая тошнота выше порога',
        'value' => 41,
        'expected' => 30,
    ]]]);

    Http::fake(['browser.test:8081/*' => Http::response(measurement(), 200)]);

    runJob(new BrowserAuditJob($result->id, $result->url));

    $result->refresh();

    $muted = array_values(array_filter($result->findings, fn (array $f): bool => ($f['muted'] ?? false) === true));

    expect(array_column($muted, 'code'))->toContain('browser.mobile.contrast')
        ->and($result->issues_muted)->toBe(count($muted));

    $visible = array_values(array_filter($result->findings, fn (array $f): bool => ($f['muted'] ?? false) !== true));

    expect($result->issues_critical + $result->issues_warning + $result->issues_notice)->toBe(count($visible));
});

test('оценка Lighthouse копится в метриках прогона', function () {
    config(['audit.browser.lighthouse' => true]);

    Http::fake(['browser.test:8081/lighthouse' => Http::response([
        'score' => 78,
        'form_factor' => 'mobile',
        'metrics' => ['lcp' => ['score' => 0.6, 'value' => 3100, 'display' => '3,1 с']],
        'opportunities' => [['title' => 'Отложить неиспользуемый CSS', 'saving_ms' => 640]],
    ], 200)]);

    $result = resultFor();
    $audit = $result->audit;

    runJob(new LighthouseJob($audit->id, 'https://test.com/'));

    $lighthouse = $audit->refresh()->metrics['lighthouse'];

    expect($lighthouse)->toHaveCount(1)
        ->and($lighthouse[0]['score'])->toBe(78)
        ->and($lighthouse[0]['url'])->toBe('https://test.com/')
        ->and($lighthouse[0]['opportunities'][0]['saving_ms'])->toBe(640);
});

test('молчащий Lighthouse не пишет нулевую оценку', function () {
    config(['audit.browser.lighthouse' => true]);
    Http::fake(['browser.test:8081/lighthouse' => Http::response('', 500)]);

    $result = resultFor();
    $audit = $result->audit;

    runJob(new LighthouseJob($audit->id, 'https://test.com/'));

    expect($audit->refresh()->metrics ?? [])->not->toHaveKey('lighthouse');
});
