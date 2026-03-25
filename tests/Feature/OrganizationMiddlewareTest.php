<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Register test routes for middleware testing
    Route::middleware(['auth:sanctum', 'org'])->get('/api/test/org', function (Request $request) {
        return response()->json([
            'org_id' => $request->get('organization')->id,
            'role' => $request->get('organization_role'),
        ]);
    });

    Route::middleware(['auth:sanctum', 'org', 'org.role:manager'])->get('/api/test/manager', function () {
        return response()->json(['ok' => true]);
    });
});

test('org middleware requires X-Organization-Id header', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/test/org')
        ->assertStatus(400);
});

test('org middleware rejects non-member', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Other', 'slug' => 'other']);

    $this->actingAs($user)
        ->getJson('/api/test/org', ['X-Organization-Id' => $org->id])
        ->assertStatus(403);
});

test('org middleware passes for member', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'My Org', 'slug' => 'my-org']);
    $org->users()->attach($user->id, ['role' => 'admin']);

    $response = $this->actingAs($user)
        ->getJson('/api/test/org', ['X-Organization-Id' => $org->id]);

    $response->assertOk()
        ->assertJsonPath('org_id', $org->id)
        ->assertJsonPath('role', 'admin');
});

test('role middleware allows sufficient role', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
    $org->users()->attach($user->id, ['role' => 'admin']);

    $this->actingAs($user)
        ->getJson('/api/test/manager', ['X-Organization-Id' => $org->id])
        ->assertOk();
});

test('role middleware blocks insufficient role', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
    $org->users()->attach($user->id, ['role' => 'viewer']);

    $this->actingAs($user)
        ->getJson('/api/test/manager', ['X-Organization-Id' => $org->id])
        ->assertStatus(403);
});
