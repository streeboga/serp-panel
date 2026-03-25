<?php

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFullHierarchy(): array
{
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $org->users()->attach($user->id, ['role' => 'admin']);
    $project = Project::create(['organization_id' => $org->id, 'name' => 'Test Project']);
    $domain = Domain::create(['project_id' => $project->id, 'name' => 'test.com', 'is_own' => true]);
    $category = Category::create(['domain_id' => $domain->id, 'name' => 'Main']);
    $cluster = Cluster::create(['category_id' => $category->id, 'name' => 'Cluster 1']);
    $region = Region::first() ?? Region::create([
        'engine' => 'google',
        'code' => 'US',
        'name' => 'USA',
        'google_gl' => 'us',
        'google_hl' => 'en',
    ]);

    return compact('user', 'org', 'project', 'domain', 'category', 'cluster', 'region');
}

test('can bulk create keywords', function () {
    $h = createFullHierarchy();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/keywords/bulk', [
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
        ], ['X-Organization-Id' => $h['org']->id]);

    $response->assertStatus(201)
        ->assertJsonCount(2);

    $this->assertDatabaseHas('keywords', ['keyword' => 'seo tools']);
    $this->assertDatabaseHas('keywords', ['keyword' => 'serp checker']);
});

test('can list keywords with filters', function () {
    $h = createFullHierarchy();

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

    // List all
    $response = $this->actingAs($h['user'])
        ->getJson('/api/keywords', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('total', 2);

    // Filter by engine
    $response = $this->actingAs($h['user'])
        ->getJson('/api/keywords?filter[engine]=google', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('total', 1);

    // Filter by partial keyword
    $response = $this->actingAs($h['user'])
        ->getJson('/api/keywords?filter[keyword]=seo', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('total', 2);
});

test('can bulk delete keywords', function () {
    $h = createFullHierarchy();

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

    $response = $this->actingAs($h['user'])
        ->deleteJson('/api/keywords/bulk', [
            'ids' => [$kw1->id, $kw2->id],
        ], ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk();
    $this->assertDatabaseMissing('keywords', ['id' => $kw1->id]);
    $this->assertDatabaseMissing('keywords', ['id' => $kw2->id]);
});

test('keywords are scoped to organization', function () {
    $h = createFullHierarchy();

    // Create keyword in our org
    Keyword::create([
        'keyword' => 'our keyword',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    // Create keyword in another org
    $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other']);
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

    $response = $this->actingAs($h['user'])
        ->getJson('/api/keywords', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('total', 1);
});
