<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CompetitorController;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\CompetitorService;

covers(CompetitorController::class, CompetitorService::class);

// === Story 4.3: Competitors aggregation ===

test('competitors endpoint returns correct structure', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'test query',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $snapshot = SerpSnapshot::create([
        'keyword_id' => $kw->id,
        'collected_at' => now()->toDateString(),
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    SerpResult::insert([
        [
            'snapshot_id' => $snapshot->id,
            'collected_at' => now()->toDateString(),
            'position' => 1,
            'url' => 'https://test.com/page',
            'domain' => 'test.com',
            'title' => 'Result',
            'description' => 'Desc',
            'snippet_type' => 'organic',
            'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id,
            'collected_at' => now()->toDateString(),
            'position' => 5,
            'url' => 'https://competitor.com/page',
            'domain' => 'competitor.com',
            'title' => 'Competitor',
            'description' => 'Desc',
            'snippet_type' => 'organic',
            'is_ads' => false,
        ],
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/serp/competitors?project_id={$h['project']->id}", orgHeaders($h['org']));

    $response->assertOk();

    $data = $response->json();
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

test('competitors returns empty for project with no keywords', function () {
    $h = createUserWithOrg();

    $project = Project::create([
        'organization_id' => $h['org']->id,
        'name' => 'Empty Project',
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/serp/competitors?project_id={$project->id}", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJson([]);
});

test('competitors requires project_id', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/serp/competitors', orgHeaders($h['org']))
        ->assertStatus(422);
});

test('competitor pages lists ranking URLs for phrases we do not rank for', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'поддержка laravel',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $snapshot = SerpSnapshot::create([
        'keyword_id' => $kw->id,
        'collected_at' => '2026-07-27',
        'search_engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    // Only competitors rank here — our own domain (test.com) is absent.
    SerpResult::insert([
        [
            'snapshot_id' => $snapshot->id, 'collected_at' => '2026-07-27', 'position' => 1,
            'url' => 'https://webit.ru/support/laravel/', 'domain' => 'webit.ru',
            'title' => 'Поддержка Laravel', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id, 'collected_at' => '2026-07-27', 'position' => 2,
            'url' => 'https://webit.ru/blog/laravel/', 'domain' => 'webit.ru',
            'title' => 'Блог', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id, 'collected_at' => '2026-07-27', 'position' => 3,
            'url' => 'https://sibdev.pro/laravel', 'domain' => 'sibdev.pro',
            'title' => 'Sibdev', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ],
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/serp/competitors/pages?project_id='.$h['project']->id, orgHeaders($h['org']));

    $response->assertOk();
    $pages = $response->json('data');

    // Every competitor URL is listed, with the phrase and position it ranks at.
    expect($pages)->toHaveCount(3);
    expect(collect($pages)->pluck('url')->all())->toContain('https://webit.ru/blog/laravel/');
    $top = collect($pages)->firstWhere('url', 'https://webit.ru/support/laravel/');
    expect($top)->toMatchArray([
        'domain' => 'webit.ru',
        'position' => 1,
        'keyword' => 'поддержка laravel',
        'is_own' => false,
    ]);
});

test('competitor pages can be filtered to one domain', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'доработка laravel',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $snapshot = SerpSnapshot::create([
        'keyword_id' => $kw->id, 'collected_at' => '2026-07-27', 'search_engine' => 'yandex',
        'device' => 'desktop', 'region_id' => $h['region']->id, 'total_results' => 100,
    ]);

    SerpResult::insert([
        [
            'snapshot_id' => $snapshot->id, 'collected_at' => '2026-07-27', 'position' => 4,
            'url' => 'https://a.ru/x', 'domain' => 'a.ru',
            'title' => 'A', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id, 'collected_at' => '2026-07-27', 'position' => 7,
            'url' => 'https://b.ru/y', 'domain' => 'b.ru',
            'title' => 'B', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ],
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/serp/competitors/pages?project_id='.$h['project']->id.'&domain=b.ru', orgHeaders($h['org']));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.domain'))->toBe('b.ru');
});

test('competitors cover every keyword, not just one snapshot per day', function () {
    $h = createFullStack();
    $today = now()->toDateString();

    // Two keywords collected the same day — both must be in scope.
    foreach ([['kw one', 'alpha.ru'], ['kw two', 'beta.ru']] as [$phrase, $domain]) {
        $kw = Keyword::create([
            'keyword' => $phrase, 'cluster_id' => $h['cluster']->id,
            'engine' => 'yandex', 'device' => 'desktop', 'region_id' => $h['region']->id,
        ]);
        $snap = SerpSnapshot::create([
            'keyword_id' => $kw->id, 'collected_at' => $today, 'search_engine' => 'yandex',
            'device' => 'desktop', 'region_id' => $h['region']->id, 'total_results' => 100,
        ]);
        SerpResult::insert([[
            'snapshot_id' => $snap->id, 'collected_at' => $today, 'position' => 1,
            'url' => "https://{$domain}/p", 'domain' => $domain,
            'title' => 'T', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ]]);
    }

    $domains = collect(
        $this->actingAs($h['user'])
            ->getJson('/api/v1/serp/competitors?project_id='.$h['project']->id, orgHeaders($h['org']))
            ->json('data')
    )->pluck('domain');

    expect($domains)->toContain('alpha.ru')->toContain('beta.ru');
});
