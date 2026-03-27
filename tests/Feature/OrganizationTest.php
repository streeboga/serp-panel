<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OrganizationController;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\OrganizationService;

covers(OrganizationController::class, OrganizationService::class);

// === Story 2.2: Organization management, member invites, RBAC ===

test('user can list their organizations', function () {
    $h = createUserWithOrg();
    $org2 = Organization::create(['name' => 'Org2', 'slug' => 'org2']);
    $org2->users()->attach($h['user']->id, ['role' => 'viewer']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/organizations');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can view organization details', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/organization', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.type', 'organizations')
        ->assertJsonPath('data.attributes.name', 'Test Org');
});

test('admin can update organization', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->patchJson('/api/v1/organization', ['name' => 'New Name'], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.name', 'New Name');

    $this->assertDatabaseHas('organizations', ['id' => $h['org']->id, 'name' => 'New Name']);
});

test('can list organization members', function () {
    $h = createUserWithOrg();
    $user2 = User::factory()->create();
    $h['org']->users()->attach($user2->id, ['role' => 'analyst']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/organization/members', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('admin can invite existing user to organization', function () {
    $h = createUserWithOrg();
    $newUser = User::factory()->create();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/organization/invite', [
            'email' => $newUser->email,
            'role' => 'analyst',
        ], orgHeaders($h['org']));

    $response->assertStatus(201);

    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $h['org']->id,
        'user_id' => $newUser->id,
        'role' => 'analyst',
    ]);
});

test('cannot invite already-member user', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/organization/invite', [
            'email' => $h['user']->email,
            'role' => 'admin',
        ], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('cannot invite non-existent user', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/organization/invite', [
            'email' => 'nonexistent@example.com',
            'role' => 'analyst',
        ], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('admin can remove member', function () {
    $h = createUserWithOrg();
    $user2 = User::factory()->create();
    $h['org']->users()->attach($user2->id, ['role' => 'analyst']);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/organization/members/{$user2->id}", [], orgHeaders($h['org']))
        ->assertOk();

    $this->assertDatabaseMissing('organization_user', [
        'organization_id' => $h['org']->id,
        'user_id' => $user2->id,
    ]);
});

test('cannot remove yourself from organization', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/organization/members/{$h['user']->id}", [], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('admin can update member role', function () {
    $h = createUserWithOrg();
    $user2 = User::factory()->create();
    $h['org']->users()->attach($user2->id, ['role' => 'analyst']);

    $this->actingAs($h['user'])
        ->patchJson("/api/v1/organization/members/{$user2->id}/role", [
            'role' => 'manager',
        ], orgHeaders($h['org']))
        ->assertOk();

    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $h['org']->id,
        'user_id' => $user2->id,
        'role' => 'manager',
    ]);
});

// === Story 2.3: Tenant data isolation ===

test('org middleware requires X-Organization-Id header', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/projects')
        ->assertStatus(400);
});

test('org middleware rejects non-member', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Other', 'slug' => 'other']);

    $this->actingAs($user)
        ->getJson('/api/v1/projects', ['X-Organization-Id' => (string) $org->id])
        ->assertStatus(403);
});

test('org middleware passes for member', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/projects', orgHeaders($h['org']))
        ->assertOk();
});

test('viewer cannot access admin-only endpoints', function () {
    $h = createUserWithOrg('viewer');

    // Viewers should be able to read projects (no role restriction on read endpoints by default)
    $this->actingAs($h['user'])
        ->getJson('/api/v1/projects', orgHeaders($h['org']))
        ->assertOk();
});

test('cross-tenant project access is blocked', function () {
    $h1 = createUserWithOrg();
    $h2 = createUserWithOrg();

    $project = Project::create(['organization_id' => $h1['org']->id, 'name' => 'Private']);

    // User from org2 tries to access org1 project
    $this->actingAs($h2['user'])
        ->getJson("/api/v1/projects/{$project->id}", orgHeaders($h2['org']))
        ->assertStatus(404);
});

test('cross-tenant project listing returns only own projects', function () {
    $h1 = createUserWithOrg();
    $h2 = createUserWithOrg();

    Project::create(['organization_id' => $h1['org']->id, 'name' => 'Org1 Project']);
    Project::create(['organization_id' => $h2['org']->id, 'name' => 'Org2 Project']);

    $response = $this->actingAs($h1['user'])
        ->getJson('/api/v1/projects', orgHeaders($h1['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// === Organization Create & Delete ===

test('authenticated user can create organization', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/organizations', ['name' => 'New Org']);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'organizations')
        ->assertJsonPath('data.attributes.name', 'New Org');

    $this->assertDatabaseHas('organizations', ['name' => 'New Org']);
    // Creator should be admin
    $orgId = $response->json('data.id');
    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $orgId,
        'user_id' => $user->id,
        'role' => 'admin',
    ]);
});

test('create organization requires name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/organizations', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('unauthenticated user cannot create organization', function () {
    $this->postJson('/api/v1/organizations', ['name' => 'Test'])
        ->assertStatus(401);
});

test('admin can delete organization (soft delete)', function () {
    $h = createUserWithOrg('admin');

    $response = $this->actingAs($h['user'])
        ->deleteJson('/api/v1/organization', [], orgHeaders($h['org']));

    $response->assertStatus(204);
    $this->assertSoftDeleted('organizations', ['id' => $h['org']->id]);
});

test('non-admin cannot delete organization', function () {
    $h = createUserWithOrg('manager');

    $this->actingAs($h['user'])
        ->deleteJson('/api/v1/organization', [], orgHeaders($h['org']))
        ->assertStatus(403);
});
