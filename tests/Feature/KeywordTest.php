<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\KeywordController;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Project;
use App\Services\KeywordService;

covers(KeywordController::class, KeywordService::class);

// === Story 3.3: Keywords management + CSV bulk import ===

test('can bulk create keywords', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/keywords/bulk', [
            'keywords' => [
                [
                    'keyword' => 'seo tools',
                    'cluster_id' => $h['cluster']->id,
                    'engine' => 'google',
                    'device' => 'desktop',
                    'region_id' => $h['region']->id,
                ],
                [
                    'keyword' => 'serp checker',
                    'cluster_id' => $h['cluster']->id,
                    'engine' => 'google',
                    'device' => 'mobile',
                    'region_id' => $h['region']->id,
                ],
            ],
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('keywords', ['keyword' => 'seo tools']);
    $this->assertDatabaseHas('keywords', ['keyword' => 'serp checker']);
});

test('can list keywords with filters', function () {
    $h = createFullStack();

    Keyword::create([
        'keyword' => 'seo tools',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);
    Keyword::create([
        'keyword' => 'yandex seo',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    // All
    $this->actingAs($h['user'])
        ->getJson('/api/v1/keywords', orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    // Filter by engine
    $this->actingAs($h['user'])
        ->getJson('/api/v1/keywords?filter[engine]=google', orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('can import keywords from list', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/keywords/import', [
            'keywords' => ['keyword one', 'keyword two', 'keyword three'],
            'cluster_id' => $h['cluster']->id,
            'engine' => 'google',
            'device' => 'desktop',
            'region_id' => $h['region']->id,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('keywords', ['keyword' => 'keyword one']);
    $this->assertDatabaseHas('keywords', ['keyword' => 'keyword two']);
    $this->assertDatabaseHas('keywords', ['keyword' => 'keyword three']);
});

test('can update keyword', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'old keyword',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/keywords/{$kw->id}", [
            'keyword' => 'new keyword',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.keyword', 'new keyword');
});

test('can bulk delete keywords', function () {
    $h = createFullStack();

    $kw1 = Keyword::create([
        'keyword' => 'keyword 1',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);
    $kw2 = Keyword::create([
        'keyword' => 'keyword 2',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $this->actingAs($h['user'])
        ->deleteJson('/api/v1/keywords/bulk', [
            'ids' => [$kw1->id, $kw2->id],
        ], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('keywords', ['id' => $kw1->id]);
    $this->assertDatabaseMissing('keywords', ['id' => $kw2->id]);
});

test('keywords are scoped to organization', function () {
    $h = createFullStack();

    Keyword::create([
        'keyword' => 'our keyword',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    // Create keyword in another org
    $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other-kw']);
    $otherProject = Project::create(['organization_id' => $otherOrg->id, 'name' => 'Other']);
    $otherDomain = Domain::create(['project_id' => $otherProject->id, 'name' => 'other.com', 'is_own' => true]);
    $otherCategory = Category::create(['domain_id' => $otherDomain->id, 'name' => 'Other']);
    $otherCluster = Cluster::create(['category_id' => $otherCategory->id, 'name' => 'Other']);
    Keyword::create([
        'keyword' => 'other keyword',
        'cluster_id' => $otherCluster->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $this->actingAs($h['user'])
        ->getJson('/api/v1/keywords', orgHeaders($h['org']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('import rejects empty keywords array', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/keywords/import', [
            'keywords' => [],
            'cluster_id' => $h['cluster']->id,
            'engine' => 'google',
            'region_id' => $h['region']->id,
        ], orgHeaders($h['org']))
        ->assertStatus(422);
});
