<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can register with organization', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'Test Org',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'organization', 'token']);

    $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
    $this->assertDatabaseHas('organization_user', ['role' => 'admin']);
});

test('user can login', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
    $org->users()->attach($user->id, ['role' => 'admin']);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonStructure(['user', 'token']);
});

test('login fails with wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422);
});

test('authenticated user can get profile', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
    $org->users()->attach($user->id, ['role' => 'admin']);

    $response = $this->actingAs($user)->getJson('/api/auth/me');

    $response->assertOk()->assertJsonPath('user.id', $user->id);
});

test('unauthenticated user cannot access protected routes', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});
