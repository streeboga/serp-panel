<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Models\Organization;
use App\Services\ApiTokenService;

covers(ApiTokenController::class, ApiTokenService::class);

// === API Token Management ===

test('user can list their tokens', function () {
    $h = createUserWithOrg();

    // Create a token first
    $h['user']->createToken("api:org:{$h['org']->id}:test-token", ['org:'.$h['org']->id.':read']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/tokens', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'test-token');
});

test('user can create token with viewer role', function () {
    $h = createUserWithOrg('admin');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', [
            'name' => 'My API Key',
            'role' => 'viewer',
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'plain_text_token', 'abilities']]);

    // Token should have read ability
    $abilities = $response->json('data.abilities');
    expect($abilities)->toContain("org:{$h['org']->id}:read");
});

test('user can create token with project scope', function () {
    $h = createFullStack('admin');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', [
            'name' => 'Project Key',
            'role' => 'viewer',
            'project_id' => $h['project']->id,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);

    $abilities = $response->json('data.abilities');
    expect($abilities[0])->toContain("project:{$h['project']->id}:");
});

test('cannot create token with role higher than own', function () {
    $h = createUserWithOrg('viewer');

    $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', [
            'name' => 'Elevated',
            'role' => 'manager',
        ], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('cannot create token for project in another org', function () {
    $h1 = createFullStack('admin');
    $h2 = createFullStack('admin');

    $this->actingAs($h1['user'])
        ->postJson('/api/v1/tokens', [
            'name' => 'Cross-org',
            'role' => 'viewer',
            'project_id' => $h2['project']->id,
        ], orgHeaders($h1['org']))
        ->assertStatus(422);
});

test('user can revoke their token', function () {
    $h = createUserWithOrg();

    $token = $h['user']->createToken("api:org:{$h['org']->id}:to-revoke", ['read']);
    $tokenId = $token->accessToken->id;

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/tokens/{$tokenId}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

test('create token requires name', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['role' => 'viewer'], orgHeaders($h['org']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('create token requires valid role', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['name' => 'test', 'role' => 'superadmin'], orgHeaders($h['org']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('tokens are scoped to current organization', function () {
    $h = createUserWithOrg();
    $org2 = Organization::create(['name' => 'Org2', 'slug' => 'org2']);
    $org2->users()->attach($h['user']->id, ['role' => 'admin']);

    // Create token in org1
    $h['user']->createToken("api:org:{$h['org']->id}:org1-token", ['read']);
    // Create token in org2
    $h['user']->createToken("api:org:{$org2->id}:org2-token", ['read']);

    // List tokens for org1 — should only see org1's token
    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/tokens', orgHeaders($h['org']));

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('org1-token');
});
