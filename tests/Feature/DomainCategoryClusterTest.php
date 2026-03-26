<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClusterController;
use App\Http\Controllers\Api\V1\DomainController;

covers(DomainController::class, CategoryController::class, ClusterController::class);

// === Story 3.2: Domains, Categories, Clusters CRUD ===

// --- Domains ---

test('can list domains for project', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/projects/{$h['project']->id}/domains", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'domains');
});

test('can create domain for project', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson("/api/v1/projects/{$h['project']->id}/domains", [
            'name' => 'newsite.com',
            'is_own' => false,
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonPath('data.attributes.name', 'newsite.com');

    $this->assertDatabaseHas('domains', ['name' => 'newsite.com', 'project_id' => $h['project']->id]);
});

test('can update domain', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/domains/{$h['domain']->id}", [
            'name' => 'updated.com',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.name', 'updated.com');
});

test('can delete domain', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/domains/{$h['domain']->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('domains', ['id' => $h['domain']->id]);
});

test('cannot access domain from other org project', function () {
    $h1 = createFullStack();
    $h2 = createUserWithOrg();

    $this->actingAs($h2['user'])
        ->getJson("/api/v1/projects/{$h1['project']->id}/domains", orgHeaders($h2['org']))
        ->assertStatus(404);
});

// --- Categories ---

test('can list categories for domain', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/domains/{$h['domain']->id}/categories", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create category', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/categories', [
            'domain_id' => $h['domain']->id,
            'name' => 'New Category',
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonPath('data.attributes.name', 'New Category');
});

test('can create nested category', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/categories', [
            'domain_id' => $h['domain']->id,
            'name' => 'Child Category',
            'parent_id' => $h['category']->id,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('categories', [
        'name' => 'Child Category',
        'parent_id' => $h['category']->id,
    ]);
});

test('can update category', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/categories/{$h['category']->id}", [
            'name' => 'Updated Category',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.name', 'Updated Category');
});

test('can delete category', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/categories/{$h['category']->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('categories', ['id' => $h['category']->id]);
});

// --- Clusters ---

test('can list clusters for category', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/categories/{$h['category']->id}/clusters", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create cluster', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/clusters', [
            'category_id' => $h['category']->id,
            'name' => 'New Cluster',
        ], orgHeaders($h['org']));

    $response->assertStatus(201)
        ->assertJsonPath('data.attributes.name', 'New Cluster');
});

test('can update cluster', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/clusters/{$h['cluster']->id}", [
            'name' => 'Updated Cluster',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('data.attributes.name', 'Updated Cluster');
});

test('can delete cluster', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/clusters/{$h['cluster']->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('clusters', ['id' => $h['cluster']->id]);
});

test('can list clusters by project', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/projects/{$h['project']->id}/clusters", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});
