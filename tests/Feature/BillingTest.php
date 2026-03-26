<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BillingController;
use App\Models\Keyword;
use App\Models\Project;
use App\Services\BillingService;

covers(BillingController::class, BillingService::class);

// === Story 8.3: Basic billing tier limits ===

test('can view billing usage', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/billing/usage', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonStructure([
            'tier',
            'limits' => ['max_keywords', 'max_projects', 'max_scrapers'],
            'usage' => ['keywords', 'projects', 'scrapers'],
        ]);
});

test('free tier has correct limits', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/billing/usage', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('tier', 'free')
        ->assertJsonPath('limits.max_keywords', 100)
        ->assertJsonPath('limits.max_projects', 3)
        ->assertJsonPath('limits.max_scrapers', 1);
});

test('can update billing tier', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->patchJson('/api/v1/billing/tier', [
            'tier' => 'pro',
        ], orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('tier', 'pro')
        ->assertJsonPath('limits.max_keywords', 10000);

    $this->assertDatabaseHas('organizations', [
        'id' => $h['org']->id,
        'billing_tier' => 'pro',
    ]);
});

test('invalid tier is rejected', function () {
    $h = createUserWithOrg();

    $this->actingAs($h['user'])
        ->patchJson('/api/v1/billing/tier', [
            'tier' => 'invalid_tier',
        ], orgHeaders($h['org']))
        ->assertStatus(422);
});

test('billing service can check limits', function () {
    $h = createFullStack();
    $service = app(BillingService::class);

    // Free tier allows 3 projects, we have 1
    expect($service->checkLimit($h['org'], 'projects'))->toBeTrue();

    // Add projects to hit the limit
    Project::create(['organization_id' => $h['org']->id, 'name' => 'P2']);
    Project::create(['organization_id' => $h['org']->id, 'name' => 'P3']);

    expect($service->checkLimit($h['org'], 'projects'))->toBeFalse();
});

test('usage reports correct counts', function () {
    $h = createFullStack();

    Keyword::create([
        'keyword' => 'kw1',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/billing/usage', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('usage.keywords', 1)
        ->assertJsonPath('usage.projects', 1)
        ->assertJsonPath('usage.scrapers', 0);
});

test('upgraded tier has higher limits', function () {
    $h = createUserWithOrg();
    $service = app(BillingService::class);

    $service->updateTier($h['org'], 'enterprise');

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/billing/usage', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonPath('tier', 'enterprise')
        ->assertJsonPath('limits.max_keywords', 100000)
        ->assertJsonPath('limits.max_projects', 500);
});
