<?php

declare(strict_types=1);

use App\Models\ClassificationRule;
use App\Models\Organization;
use App\Models\SiteType;
use App\Services\ClassificationService;

covers(ClassificationService::class);

test('returns null when no rules match', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-cls']);

    $service = app(ClassificationService::class);
    $result = $service->classify('unknown.com', $org->id);

    expect($result)->toBeNull();
});

test('domain_regex rule works', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-regex']);
    $type = SiteType::create(['slug' => 'gov', 'name' => 'Government', 'color' => '#333']);

    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_regex',
        'pattern' => '/\.gov\.\w+$/',
        'site_type_id' => $type->id,
        'priority' => 10,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('example.gov.ru', $org->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
});

test('title_contains rule works', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test-title']);
    $type = SiteType::create(['slug' => 'wiki', 'name' => 'Wiki', 'color' => '#aaa']);

    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'title_contains',
        'pattern' => 'wikipedia',
        'site_type_id' => $type->id,
        'priority' => 5,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('en.wikipedia.org', $org->id, null, 'Wikipedia - The Free Encyclopedia');

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
});

test('system rules apply to all organizations', function () {
    $org1 = Organization::create(['name' => 'Org1', 'slug' => 'org1-sys']);
    $org2 = Organization::create(['name' => 'Org2', 'slug' => 'org2-sys']);
    $type = SiteType::create(['slug' => 'social', 'name' => 'Social', 'color' => '#00f']);

    ClassificationRule::create([
        'organization_id' => $org1->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'facebook.com',
        'site_type_id' => $type->id,
        'priority' => 100,
        'is_system' => true,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('facebook.com', $org2->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
});
