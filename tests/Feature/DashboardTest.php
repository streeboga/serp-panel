<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DashboardController;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\DashboardService;

covers(DashboardController::class, DashboardService::class);

// === Story 4.2: Dashboard Summary API ===

test('dashboard summary returns correct structure', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/dashboard/summary?project_id={$h['project']->id}", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonStructure([
            'total_keywords',
            'positions' => ['top3', 'top10', 'top20', 'top100'],
        ]);
});

test('dashboard summary with keyword data', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $snapshot = SerpSnapshot::create([
        'keyword_id' => $kw->id,
        'collected_at' => '2026-03-25',
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    SerpResult::create([
        'snapshot_id' => $snapshot->id,
        'collected_at' => '2026-03-25',
        'position' => 5,
        'url' => 'https://test.com/page',
        'domain' => 'test.com',
        'title' => 'Result',
        'description' => 'Desc',
        'snippet_type' => 'organic',
        'is_ads' => false,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/dashboard/summary?project_id={$h['project']->id}", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('total_keywords', 1)
        ->assertJsonPath('positions.top10', 1);
});

test('dashboard requires project_id', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/dashboard/summary', orgHeaders($h['org']))
        ->assertStatus(422);
});
