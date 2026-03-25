<?php

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createProjectHierarchy(): array
{
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $org->users()->attach($user->id, ['role' => 'admin']);
    $project = Project::create(['organization_id' => $org->id, 'name' => 'Test Project']);
    $domain = Domain::create(['project_id' => $project->id, 'name' => 'test.com', 'is_own' => true]);
    $category = Category::create(['domain_id' => $domain->id, 'name' => 'Main']);
    $cluster = Cluster::create(['category_id' => $category->id, 'name' => 'Cluster 1']);

    return compact('user', 'org', 'project', 'domain', 'category', 'cluster');
}

test('can list projects', function () {
    $h = createProjectHierarchy();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/projects', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonCount(1);
});

test('can create project', function () {
    $h = createProjectHierarchy();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/projects', [
            'name' => 'New Project',
            'description' => 'A description',
        ], ['X-Organization-Id' => $h['org']->id]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'New Project');

    $this->assertDatabaseHas('projects', ['name' => 'New Project', 'organization_id' => $h['org']->id]);
});

test('can show project with domains count', function () {
    $h = createProjectHierarchy();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/projects/'.$h['project']->id, ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('name', 'Test Project')
        ->assertJsonPath('domains_count', 1);
});

test('can update project', function () {
    $h = createProjectHierarchy();

    $response = $this->actingAs($h['user'])
        ->putJson('/api/projects/'.$h['project']->id, [
            'name' => 'Updated Name',
        ], ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonPath('name', 'Updated Name');
});

test('can delete project', function () {
    $h = createProjectHierarchy();

    $response = $this->actingAs($h['user'])
        ->deleteJson('/api/projects/'.$h['project']->id, [], ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk();
    $this->assertDatabaseMissing('projects', ['id' => $h['project']->id]);
});

test('projects are scoped to organization', function () {
    $h = createProjectHierarchy();

    // Create another org with a project
    $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other']);
    $otherOrg->users()->attach($h['user']->id, ['role' => 'admin']);
    Project::create(['organization_id' => $otherOrg->id, 'name' => 'Other Project']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/projects', ['X-Organization-Id' => $h['org']->id]);

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Test Project');
});

test('cannot access other org project', function () {
    $h = createProjectHierarchy();

    $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other']);
    $otherOrg->users()->attach($h['user']->id, ['role' => 'admin']);
    $otherProject = Project::create(['organization_id' => $otherOrg->id, 'name' => 'Other Project']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/projects/'.$otherProject->id, ['X-Organization-Id' => $h['org']->id]);

    $response->assertStatus(404);
});
