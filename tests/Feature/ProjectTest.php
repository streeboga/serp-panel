<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProjectController;
use App\Models\Project;
use App\Services\ProjectService;

covers(ProjectController::class, ProjectService::class);

// === Story 3.1: Projects CRUD ===

test('can list projects', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/projects', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'projects');
});

test('can create project', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/projects', [
            'name' => 'New Project',
            'description' => 'A description',
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'projects')
        ->assertJsonPath('data.attributes.name', 'New Project');

    $this->assertDatabaseHas('projects', [
        'name' => 'New Project',
        'organization_id' => $h['org']->id,
    ]);
});

test('cannot create project without name', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/projects', [], orgHeaders($h['org']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('can show project', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/projects/{$h['project']->id}", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.type', 'projects')
        ->assertJsonPath('data.attributes.name', 'Test Project');
});

test('can update project', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}", [
            'name' => 'Updated Name',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.name', 'Updated Name');
});

test('can delete project', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/projects/{$h['project']->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('projects', ['id' => $h['project']->id]);
});

test('projects are scoped to organization', function () {
    $h1 = createUserWithOrg();
    $h2 = createUserWithOrg();

    Project::create(['organization_id' => $h1['org']->id, 'name' => 'Org1 Project']);
    Project::create(['organization_id' => $h2['org']->id, 'name' => 'Org2 Project']);

    $response = $this->actingAs($h1['user'])
        ->getJson('/api/v1/projects', orgHeaders($h1['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('cannot access other org project', function () {
    $h1 = createUserWithOrg();
    $h2 = createUserWithOrg();

    $project = Project::create(['organization_id' => $h1['org']->id, 'name' => 'Private']);

    $this->actingAs($h2['user'])
        ->getJson("/api/v1/projects/{$project->id}", orgHeaders($h2['org']))
        ->assertStatus(404);
});
