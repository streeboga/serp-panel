<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ExportController;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\ExportService;

covers(ExportController::class, ExportService::class);

// === Story 8.2: Data export CSV ===

test('can export keywords as csv', function () {
    $h = createFullStack();
    Keyword::create([
        'keyword' => 'seo tools',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/export/keywords', orgHeaders($h['org']));

    $response->assertOk()
        ->assertDownload('keywords.csv');
});

test('keywords export contains correct data', function () {
    $h = createFullStack();
    Keyword::create([
        'keyword' => 'seo tools',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/export/keywords', orgHeaders($h['org']));

    $content = $response->streamedContent();
    expect($content)->toContain('seo tools');
    expect($content)->toContain('keyword');
});

test('can export serp data as csv', function () {
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
        'position' => 1,
        'url' => 'https://test.com/page',
        'domain' => 'test.com',
        'title' => 'Result',
        'description' => 'Desc',
        'snippet_type' => 'organic',
        'is_ads' => false,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/export/serp?keyword_ids[]={$kw->id}", orgHeaders($h['org']));

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('test.com');
});

test('export serp requires keyword_ids', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/export/serp', orgHeaders($h['org']))
        ->assertStatus(422);
});
