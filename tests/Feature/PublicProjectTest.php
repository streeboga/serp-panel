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
        ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'is_own', 'type']]])
        ->assertJsonPath('data.0.id', fn ($v) => is_string($v))
        ->assertJsonPath('data.0.name', 'test.com')
        ->assertJsonPath('data.0.is_own', true);
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

// === Mutation Testing Coverage ===

test('public project slug is a valid uuid', function () {
    $h = createFullStack('manager');

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));

    $slug = $response->json('data.attributes.public_slug');
    expect($slug)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('public url contains the slug', function () {
    $h = createFullStack('manager');

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));

    $slug = $response->json('data.attributes.public_slug');
    $url = $response->json('data.attributes.public_url');
    expect($url)->toContain($slug);
    expect($url)->toContain('/api/v1/public/');
});

test('toggle public requires is_public field', function () {
    $h = createFullStack('manager');

    $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", [], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('re-enabling public generates new slug', function () {
    $h = createFullStack('manager');

    // Enable
    $r1 = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));
    $slug1 = $r1->json('data.attributes.public_slug');

    // Disable
    $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => false], orgHeaders($h['org']));

    // Re-enable — should get NEW slug (since we nullify on disable)
    $r2 = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));
    $slug2 = $r2->json('data.attributes.public_slug');

    expect($slug2)->not->toBe($slug1);
});

test('enabling already-public project preserves existing slug', function () {
    $h = createFullStack('manager');

    // Enable first time
    $r1 = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));
    $slug1 = $r1->json('data.attributes.public_slug');

    // Enable again (without disabling) — slug must stay the same
    $r2 = $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));
    $slug2 = $r2->json('data.attributes.public_slug');

    expect($slug2)->toBe($slug1);
});

test('public slug is stored as string type', function () {
    $h = createFullStack('manager');

    $this->actingAs($h['user'])
        ->patchJson("/api/v1/projects/{$h['project']->id}/public", ['is_public' => true], orgHeaders($h['org']));

    $h['project']->refresh();
    expect($h['project']->public_slug)->toBeString();
    expect($h['project']->public_slug)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
