<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PublicProjectController;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Support\Str;

covers(PublicProjectController::class, ProjectController::class, ProjectService::class);

// === Public Projects ===

test('manager can enable public access on project', function () {
    $h = createFullStack('manager');

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", [
            'is_public' => true,
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.is_public', true);

    $this->assertNotNull($response->json('data.attributes.public_slug'));
    $this->assertNotNull($response->json('data.attributes.public_url'));
});

test('manager can disable public access', function () {
    $h = createFullStack('manager');

    // Enable first
    $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));

    // Disable
    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => false], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.is_public', false)
        ->assertJsonPath('data.attributes.public_slug', null);
});

test('viewer cannot toggle public access', function () {
    $h = createFullStack('viewer');

    $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']))
        ->assertStatus(403);
});

test('public project is accessible without auth', function () {
    $h = createFullStack('admin');

    // Make public
    $slug = Str::uuid()->toString();
    $h['project']->update(['is_public' => true, 'public_slug' => $slug]);

    $response = $this->getJson("/api/v1/public/{$slug}");

    $response->assertOk()
        ->assertJsonPath('data.type', 'projects')
        ->assertJsonPath('data.attributes.name', $h['project']->name);
});

test('non-public project returns 404 via public endpoint', function () {
    $h = createFullStack('admin');

    // Project is_public = false (default)
    $fakeSlug = Str::uuid()->toString();
    $this->getJson("/api/v1/public/{$fakeSlug}")
        ->assertStatus(404);
});

test('public project domains endpoint works without auth', function () {
    $h = createFullStack('admin');
    $slug = Str::uuid()->toString();
    $h['project']->update(['is_public' => true, 'public_slug' => $slug]);

    $response = $this->getJson("/api/v1/public/{$slug}/domains");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'test.com');
});

test('disabled public project becomes inaccessible', function () {
    $h = createFullStack('admin');

    // Enable then disable
    $slug = Str::uuid()->toString();
    $h['project']->update(['is_public' => true, 'public_slug' => $slug]);
    $h['project']->update(['is_public' => false, 'public_slug' => null]);

    $this->getJson("/api/v1/public/{$slug}")
        ->assertStatus(404);
});
