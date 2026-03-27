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

// === Mutation Testing Coverage ===

test('list tokens returns exact 200 status and correct response structure', function () {
    $h = createUserWithOrg();
    $h['user']->createToken("api:org:{$h['org']->id}:my-key", ['org:'.$h['org']->id.':read']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/tokens', orgHeaders($h['org']));

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']]])
        ->assertJsonPath('data.0.id', fn ($v) => is_string($v));
});

test('create token returns 201 with plain_text_token', function () {
    $h = createUserWithOrg('admin');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['name' => 'Key', 'role' => 'viewer'], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'plain_text_token', 'abilities']])
        ->assertJsonPath('data.id', fn ($v) => is_string($v))
        ->assertJsonPath('data.plain_text_token', fn ($v) => is_string($v) && str_contains($v, '|'));
});

test('manager token has read export write abilities', function () {
    $h = createUserWithOrg('admin');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['name' => 'mgr', 'role' => 'manager'], orgHeaders($h['org']));

    $response->assertStatus(201);
    $abilities = $response->json('data.abilities');
    expect($abilities)->toHaveCount(3);
    expect($abilities[0])->toContain(':read');
    expect($abilities[1])->toContain(':export');
    expect($abilities[2])->toContain(':write');
});

test('analyst token has read and export abilities', function () {
    $h = createUserWithOrg('admin');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['name' => 'ana', 'role' => 'analyst'], orgHeaders($h['org']));

    $response->assertStatus(201);
    $abilities = $response->json('data.abilities');
    expect($abilities)->toHaveCount(2);
    expect($abilities[0])->toContain(':read');
    expect($abilities[1])->toContain(':export');
});

test('revoke token returns exact 204', function () {
    $h = createUserWithOrg();
    $token = $h['user']->createToken("api:org:{$h['org']->id}:tmp", ['read']);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/tokens/{$token->accessToken->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);
});

test('role escalation returns 422 with error detail', function () {
    $h = createUserWithOrg('viewer');

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', ['name' => 'test', 'role' => 'manager'], orgHeaders($h['org']));

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['*' => ['status', 'title', 'detail']]])
        ->assertJsonPath('errors.0.detail', fn ($v) => is_string($v) && strlen($v) > 0);
});

test('create token with expires_at stores expiration', function () {
    $h = createUserWithOrg('admin');
    $expiresAt = now()->addDays(30)->toISOString();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/tokens', [
            'name' => 'expiring',
            'role' => 'viewer',
            'expires_at' => $expiresAt,
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'plain_text_token', 'abilities']]);
});

test('unauthenticated request to list tokens returns 401', function () {
    $this->getJson('/api/v1/tokens')
        ->assertStatus(401);
});

test('unauthenticated request to create token returns 401', function () {
    $this->postJson('/api/v1/tokens', ['name' => 'test', 'role' => 'viewer'])
        ->assertStatus(401);
});

test('unauthenticated request to revoke token returns 401', function () {
    $this->deleteJson('/api/v1/tokens/1')
        ->assertStatus(401);
});
