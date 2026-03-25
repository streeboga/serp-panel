<?php

use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\Organization;
use App\Models\SiteType;
use App\Services\ClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('classifies domain by exact match', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $type = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#8B5CF6']);
    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'wildberries.ru',
        'site_type_id' => $type->id,
        'priority' => 10,
    ]);

    $service = new ClassificationService;
    $result = $service->classify('wildberries.ru', $org->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
    expect($result->classified_by->value)->toBe('rule');
});

test('classifies domain by contains', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $type = SiteType::create(['slug' => 'blog', 'name' => 'Блог', 'color' => '#06B6D4']);
    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_contains',
        'pattern' => 'blog',
        'site_type_id' => $type->id,
        'priority' => 5,
    ]);

    $service = new ClassificationService;
    $result = $service->classify('myblog.com', $org->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
});

test('manual classification is not overridden', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $type1 = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#8B5CF6']);
    $type2 = SiteType::create(['slug' => 'ecommerce', 'name' => 'Магазин', 'color' => '#3B82F6']);

    DomainClassification::create([
        'domain' => 'shop.ru',
        'site_type_id' => $type1->id,
        'classified_by' => 'manual',
        'organization_id' => $org->id,
    ]);

    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_contains',
        'pattern' => 'shop',
        'site_type_id' => $type2->id,
        'priority' => 10,
    ]);

    $service = new ClassificationService;
    $result = $service->classify('shop.ru', $org->id);

    expect($result->site_type_id)->toBe($type1->id);
});

test('higher priority rule wins', function () {
    $org = Organization::create(['name' => 'Test', 'slug' => 'test']);
    $type1 = SiteType::create(['slug' => 'info', 'name' => 'Инфо', 'color' => '#10B981']);
    $type2 = SiteType::create(['slug' => 'media', 'name' => 'СМИ', 'color' => '#F97316']);

    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_contains',
        'pattern' => '.ru',
        'site_type_id' => $type1->id,
        'priority' => 1,
    ]);

    ClassificationRule::create([
        'organization_id' => $org->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'rbc.ru',
        'site_type_id' => $type2->id,
        'priority' => 10,
    ]);

    $service = new ClassificationService;
    $result = $service->classify('rbc.ru', $org->id);

    expect($result->site_type_id)->toBe($type2->id);
});
