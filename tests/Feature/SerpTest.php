<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SerpController;
use App\Models\Keyword;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\SerpService;

covers(SerpController::class, SerpService::class);

// === Story 4.1: SERP Data API & History ===

test('can list serp snapshots for keyword', function () {
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
        'collected_at' => '2026-03-25',
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    SerpResult::insert([
        [
            'snapshot_id' => $snapshot->id,
            'collected_at' => '2026-03-25',
            'position' => 1,
            'url' => 'https://test.com/page1',
            'domain' => 'test.com',
            'title' => 'Result 1',
            'description' => 'Desc 1',
            'snippet_type' => 'organic',
            'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id,
            'collected_at' => '2026-03-25',
            'position' => 2,
            'url' => 'https://example.com/page2',
            'domain' => 'example.com',
            'title' => 'Result 2',
            'description' => 'Desc 2',
            'snippet_type' => 'organic',
            'is_ads' => false,
        ],
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/serp", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can get serp history for keyword', function () {
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
        'collected_at' => '2026-03-25',
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    SerpResult::create([
        'snapshot_id' => $snapshot->id,
        'collected_at' => '2026-03-25',
        'position' => 3,
        'url' => 'https://test.com/page',
        'domain' => 'test.com',
        'title' => 'Our page',
        'description' => 'Description',
        'snippet_type' => 'organic',
        'is_ads' => false,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/serp/history", orgHeaders($h['org']));

    $response->assertOk();
});

test('serp list supports date filters', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'test query',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    SerpSnapshot::create([
        'keyword_id' => $kw->id,
        'collected_at' => '2026-03-20',
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    SerpSnapshot::create([
        'keyword_id' => $kw->id,
        'collected_at' => '2026-03-25',
        'search_engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
        'total_results' => 100,
    ]);

    // Filter to only get recent
    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/serp?from=2026-03-24", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});
