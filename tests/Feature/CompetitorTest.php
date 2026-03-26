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
            'url' => 'https://test.com/page',
            'domain' => 'test.com',
            'title' => 'Result',
            'description' => 'Desc',
            'snippet_type' => 'organic',
            'is_ads' => false,
        ],
        [
            'snapshot_id' => $snapshot->id,
            'collected_at' => '2026-03-25',
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
