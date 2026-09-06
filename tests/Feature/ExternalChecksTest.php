<?php

declare(strict_types=1);

use App\Jobs\CollectFieldDataJob;
use App\Jobs\ValidateHtmlJob;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\Audit\CruxClient;
use App\Services\Audit\HtmlValidator;
use Illuminate\Support\Facades\Http;

covers(HtmlValidator::class, CruxClient::class, ValidateHtmlJob::class, CollectFieldDataJob::class);

function w3cResponse(array $messages): array
{
    return ['url' => 'https://test.com/', 'messages' => $messages];
}

function pageResult(): PageAuditResult
{
    $h = createFullStack();

    $audit = SiteAudit::create(['project_id' => $h['project']->id, 'scope' => 'site', 'status' => 'running']);

    return PageAuditResult::create([
        'site_audit_id' => $audit->id,
        'url' => 'https://test.com/',
        'url_hash' => sha1('https://test.com/'),
        'path' => '/',
        'http_status' => 200,
        'score' => 100,
        'findings' => [],
        'metrics' => [],
    ]);
}

test('предпочтения линтера не выдаются за нарушения спецификации', function () {
    // Ровно случай eq.team: 36 сообщений, все про завершающий слэш, ошибок ноль.
    Http::fake(['validator.w3.org/*' => Http::response(w3cResponse(array_fill(0, 36, [
        'type' => 'info',
        'message' => 'Trailing slash on void elements has no effect',
        'lastLine' => 7,
    ])))]);

    $outcome = app(HtmlValidator::class)->validate('https://test.com/');

    expect($outcome['errors'])->toBe([])
        ->and($outcome['info'])->toBe(36);

    $result = pageResult();
    runJob(new ValidateHtmlJob($result->id, $result->url));

    $fresh = $result->refresh();

    // Находки быть не должно, но число замечаний обязано сохраниться в метриках.
    // Ключи сравниваем поимённо: jsonb в Postgres их переупорядочивает.
    expect($fresh->findings)->toBe([])
        ->and($fresh->metrics['w3c']['errors'])->toBe(0)
        ->and($fresh->metrics['w3c']['warnings'])->toBe(0)
        ->and($fresh->metrics['w3c']['info'])->toBe(36);
});

test('настоящие нарушения спецификации становятся находкой', function () {
    Http::fake(['validator.w3.org/*' => Http::response(w3cResponse([
        ['type' => 'error', 'message' => 'An “img” element must have an “alt” attribute', 'lastLine' => 4],
        ['type' => 'error', 'subType' => 'fatal', 'message' => 'End tag “h2” seen, but there were open elements.', 'lastLine' => 8],
        ['type' => 'info', 'subType' => 'warning', 'message' => 'Consider adding a “lang” attribute'],
        ['type' => 'info', 'message' => 'Trailing slash'],
    ]))]);

    $result = pageResult();
    runJob(new ValidateHtmlJob($result->id, $result->url));

    $fresh = $result->refresh();
    $finding = collect($fresh->findings)->firstWhere('code', 'w3c.validation.errors');

    // Есть фатальная ошибка разбора — значит критично.
    expect($finding['severity'])->toBe('critical')
        ->and($finding['value'])->toHaveCount(2)
        ->and($fresh->metrics['w3c']['errors'])->toBe(2)
        ->and($fresh->metrics['w3c']['warnings'])->toBe(1)
        ->and($fresh->metrics['w3c']['info'])->toBe(1);
});

test('молчание валидатора оставляет страницу непроверенной', function () {
    Http::fake(['validator.w3.org/*' => Http::response('', 503)]);

    $result = pageResult();
    runJob(new ValidateHtmlJob($result->id, $result->url));

    expect($result->refresh()->metrics)->not->toHaveKey('w3c');
});

test('CrUX откатывается на домен, когда по URL данных нет', function () {
    config(['audit.crux.key' => 'test-key']);

    Http::fake(function ($request) {
        // По конкретному URL данных нет — ровно как в отчёте gvozd по eq.team.
        if (str_contains($request->body(), '"url"')) {
            return Http::response(['error' => ['code' => 404]], 404);
        }

        return Http::response(['record' => [
            'metrics' => [
                'largest_contentful_paint' => ['percentiles' => ['p75' => 4200], 'histogram' => [
                    ['density' => 0.4], ['density' => 0.3], ['density' => 0.3],
                ]],
                'cumulative_layout_shift' => ['percentiles' => ['p75' => 0.05], 'histogram' => []],
            ],
            'collectionPeriod' => ['firstDate' => ['year' => 2026]],
        ]], 200);
    });

    $field = app(CruxClient::class)->forUrl('https://test.com/page/');

    expect($field['scope'])->toBe('origin')
        ->and($field['metrics']['largest_contentful_paint']['p75'])->toBe(4200)
        ->and($field['metrics']['largest_contentful_paint']['good'])->toBe(0.4);
});

test('плохой полевой LCP становится находкой прогона, хороший CLS — нет', function () {
    config(['audit.crux.key' => 'test-key']);

    Http::fake(['chromeuxreport.googleapis.com/*' => Http::response(['record' => [
        'metrics' => [
            'largest_contentful_paint' => ['percentiles' => ['p75' => 4200], 'histogram' => []],
            'cumulative_layout_shift' => ['percentiles' => ['p75' => 0.05], 'histogram' => []],
        ],
    ]], 200)]);

    $result = pageResult();
    $audit = $result->audit;

    runJob(new CollectFieldDataJob($audit->id, 'https://test.com'));

    $codes = array_column($audit->refresh()->findings, 'code');

    expect($codes)->toContain('field.largest_contentful_paint')
        ->and($codes)->not->toContain('field.cumulative_layout_shift')
        ->and($audit->metrics['field']['metrics']['largest_contentful_paint']['p75'])->toBe(4200);
});

test('без ключа CrUX не ходит в сеть', function () {
    config(['audit.crux.key' => null]);
    Http::fake();

    expect(app(CruxClient::class)->forUrl('https://test.com/'))->toBeNull();

    Http::assertNothingSent();
});
