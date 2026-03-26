<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Project;
use App\Services\BillingService;

covers(BillingService::class);

test('check limit returns true when under limit', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-bill']);
    $service = app(BillingService::class);

    expect($service->checkLimit($org, 'projects'))->toBeTrue();
    expect($service->checkLimit($org, 'keywords'))->toBeTrue();
    expect($service->checkLimit($org, 'scrapers'))->toBeTrue();
});

test('check limit returns false when at limit', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-bill-lim']);
    $service = app(BillingService::class);

    // Free tier: 3 projects max
    Project::create(['organization_id' => $org->id, 'name' => 'P1']);
    Project::create(['organization_id' => $org->id, 'name' => 'P2']);
    Project::create(['organization_id' => $org->id, 'name' => 'P3']);

    expect($service->checkLimit($org, 'projects'))->toBeFalse();
});

test('update tier changes organization limits', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-bill-upd']);
    $service = app(BillingService::class);

    $updated = $service->updateTier($org, 'pro');

    expect($updated->billing_tier)->toBe('pro');
    expect($updated->max_keywords)->toBe(10000);
    expect($updated->max_projects)->toBe(50);
    expect($updated->max_scrapers)->toBe(10);
});

test('update tier rejects invalid tier', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-bill-inv']);
    $service = app(BillingService::class);

    $service->updateTier($org, 'nonexistent');
})->throws(InvalidArgumentException::class);

test('usage returns correct structure', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-bill-usage']);
    $service = app(BillingService::class);

    $usage = $service->usage($org);

    expect($usage)->toHaveKeys(['tier', 'limits', 'usage']);
    expect($usage['tier'])->toBe('free');
    expect($usage['limits'])->toHaveKeys(['max_keywords', 'max_projects', 'max_scrapers']);
    expect($usage['usage'])->toHaveKeys(['keywords', 'projects', 'scrapers']);
});
