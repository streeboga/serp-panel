<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuthService;

covers(AuthController::class, AuthService::class);

// === Story 2.1: Auth API ===

test('user can register with organization', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'Test Org',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'user' => ['type', 'id', 'attributes'],
            'organization' => ['type', 'id', 'attributes'],
            'token',
        ]);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
    $this->assertDatabaseHas('organization_user', ['role' => 'admin']);
});

test('register validates required fields', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'organization_name']);
});

test('register rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'Org',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('register rejects weak password', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'organization_name' => 'Org',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('user can login', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
    $org->users()->attach($user->id, ['role' => 'admin']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['type', 'id', 'attributes'],
            'token',
        ]);
});

test('login fails with wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ])->assertStatus(422);
});

test('login fails with non-existent email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ])->assertStatus(422);
});

test('authenticated user can get profile', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.type', 'users')
        ->assertJsonPath('user.id', (string) $h['user']->id);
});

test('user can update profile', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->patchJson('/api/v1/auth/profile', [
            'name' => 'Updated Name',
            'locale' => 'en',
            'theme' => 'dark',
        ]);

    $response->assertOk()
        ->assertJsonPath('user.attributes.name', 'Updated Name');

    $this->assertDatabaseHas('users', [
        'id' => $h['user']->id,
        'name' => 'Updated Name',
        'locale' => 'en',
        'theme' => 'dark',
    ]);
});

test('user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

test('unauthenticated user cannot access protected routes', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('register with locale and theme', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'Test Org',
        'locale' => 'ru',
        'theme' => 'dark',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'locale' => 'ru', 'theme' => 'dark']);
});
