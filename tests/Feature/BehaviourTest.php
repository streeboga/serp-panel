<?php

declare(strict_types=1);

use App\Jobs\CollectBehaviourJob;
use App\Models\PageAuditResult;
use App\Models\SiteAudit;
use App\Services\Audit\MetrikaClient;
use Illuminate\Support\Facades\Http;

covers(MetrikaClient::class, CollectBehaviourJob::class);

function metrikaAudit(): SiteAudit
{
    $h = createFullStack();
    $h['org']->update(['yandex_token' => 'test-token']);
    $h['project']->update(['metrika_counter_id' => 12345]);

    $audit = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => 'running',
    ]);

    foreach (['https://test.com/', 'https://test.com/uslugi/'] as $url) {
        PageAuditResult::create([
            'site_audit_id' => $audit->id,
            'url' => $url,
            'url_hash' => sha1($url),
            'path' => parse_url($url, PHP_URL_PATH),
            'http_status' => 200,
            'score' => 90,
            'findings' => [],
            'metrics' => [],
        ]);
    }

    return $audit;
}

function fakeMetrika(): void
{
    Http::fake(['api-metrika.yandex.net/*' => function ($request) {
        $url = $request->url();

        if (str_contains($url, 'startURL')) {
            return Http::response(['data' => [
                ['dimensions' => [['name' => 'https://test.com/uslugi/']], 'metrics' => [420, 82.5, 1.1]],
                ['dimensions' => [['name' => 'https://test.com/']], 'metrics' => [1500, 34.2, 3.4]],
                // Мало визитов — в проблемные попасть не должна, хоть отказы и высокие.
                ['dimensions' => [['name' => 'https://test.com/redkaya/']], 'metrics' => [4, 100.0, 1.0]],
            ]], 200);
        }

        if (str_contains($url, 'avgPageLoadTime')) {
            return Http::response(['totals' => [2.41, 0.318, 0.021, 0.044]], 200);
        }

        return Http::response(['totals' => [5000, 3800, 41.2, 2.7, 96]], 200);
    }]);
}

test('сводка, тайминги и проблемные страницы приезжают в прогон', function () {
    fakeMetrika();
    $audit = metrikaAudit();

    runJob(new CollectBehaviourJob($audit->id));

    $fresh = $audit->refresh();
    $behaviour = $fresh->metrics['behaviour'];

    // jsonb схлопывает целые float'ы: 5000.0 читается обратно как 5000.
    expect($behaviour['summary']['visits'])->toEqual(5000)
        ->and($behaviour['summary']['bounce_rate'])->toBe(41.2)
        ->and($behaviour['timing']['page_load_sec'])->toBe(2.41);

    $finding = collect($fresh->findings)->firstWhere('code', 'behaviour.bounce');

    expect($finding)->not->toBeNull()
        // Отказы 82.5% на 420 визитах — повод; 100% на четырёх визитах — нет.
        ->and($finding['value'])->toHaveCount(1)
        ->and($finding['value'][0]['url'])->toBe('https://test.com/uslugi/');
});

test('поведение раскладывается по страницам прогона', function () {
    fakeMetrika();
    $audit = metrikaAudit();

    runJob(new CollectBehaviourJob($audit->id));

    $uslugi = $audit->results()->where('url', 'https://test.com/uslugi/')->firstOrFail();

    expect($uslugi->metrics['behaviour']['visits'])->toEqual(420)
        ->and($uslugi->metrics['behaviour']['bounce_rate'])->toBe(82.5);
});

test('без счётчика и токена в сеть не ходим', function () {
    Http::fake();

    $h = createFullStack();
    $audit = SiteAudit::create(['project_id' => $h['project']->id, 'scope' => 'site', 'status' => 'running']);

    runJob(new CollectBehaviourJob($audit->id));

    Http::assertNothingSent();
    expect($audit->refresh()->metrics)->not->toHaveKey('behaviour');
});

test('отказ доступа оставляет прогон без поведенческих данных', function () {
    Http::fake(['api-metrika.yandex.net/*' => Http::response(['errors' => [['message' => 'no access']]], 403)]);

    $audit = metrikaAudit();
    runJob(new CollectBehaviourJob($audit->id));

    // 403 — это «данных нет», а не нулевая посещаемость.
    expect($audit->refresh()->metrics)->not->toHaveKey('behaviour');
});
