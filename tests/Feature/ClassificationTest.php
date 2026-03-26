<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ClassificationController;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\SiteType;
use App\Services\ClassificationRuleService;
use App\Services\ClassificationService;

covers(ClassificationController::class, ClassificationRuleService::class, ClassificationService::class);

// === Story 6.1: Classification Rules CRUD ===

test('can list classification rules', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'marketplace', 'name' => 'Marketplace', 'color' => '#8B5CF6']);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'amazon.com',
        'site_type_id' => $type->id,
        'priority' => 10,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/classification/rules', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create classification rule', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'blog', 'name' => 'Blog', 'color' => '#06B6D4']);

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/classification/rules', [
            'rule_type' => 'domain_contains',
            'pattern' => 'blog',
            'site_type_id' => $type->id,
            'priority' => 5,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('classification_rules', [
        'organization_id' => $h['org']->id,
        'pattern' => 'blog',
    ]);
});

test('can update classification rule', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'info', 'name' => 'Info', 'color' => '#10B981']);

    $rule = ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'old.com',
        'site_type_id' => $type->id,
        'priority' => 5,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/classification/rules/{$rule->id}", [
            'pattern' => 'new.com',
            'priority' => 10,
        ], orgHeaders($h['org']));

    $response->assertOk();
    $this->assertDatabaseHas('classification_rules', [
        'id' => $rule->id,
        'pattern' => 'new.com',
        'priority' => 10,
    ]);
});

test('can delete classification rule', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'info', 'name' => 'Info', 'color' => '#10B981']);

    $rule = ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'test.com',
        'site_type_id' => $type->id,
        'priority' => 5,
    ]);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/classification/rules/{$rule->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('classification_rules', ['id' => $rule->id]);
});

test('can list site types', function () {
    $h = createUserWithOrg();
    SiteType::create(['slug' => 'marketplace', 'name' => 'Marketplace', 'color' => '#8B5CF6']);
    SiteType::create(['slug' => 'blog', 'name' => 'Blog', 'color' => '#06B6D4']);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/site-types', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// === Story 6.2: Auto-classification engine + manual override ===

test('can manually classify domain', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'marketplace', 'name' => 'Marketplace', 'color' => '#8B5CF6']);

    $response = $this->actingAs($h['user'])
        ->patchJson('/api/v1/domains/example.com/classify', [
            'site_type_id' => $type->id,
        ], orgHeaders($h['org']));

    $response->assertSuccessful();
    $this->assertDatabaseHas('domain_classifications', [
        'domain' => 'example.com',
        'classified_by' => 'manual',
        'organization_id' => $h['org']->id,
    ]);
});

test('classifies domain by exact match rule', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'marketplace', 'name' => 'Marketplace', 'color' => '#8B5CF6']);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'wildberries.ru',
        'site_type_id' => $type->id,
        'priority' => 10,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('wildberries.ru', $h['org']->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
    expect($result->classified_by->value)->toBe('rule');
});

test('classifies domain by contains rule', function () {
    $h = createUserWithOrg();
    $type = SiteType::create(['slug' => 'blog', 'name' => 'Blog', 'color' => '#06B6D4']);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_contains',
        'pattern' => 'blog',
        'site_type_id' => $type->id,
        'priority' => 5,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('myblog.com', $h['org']->id);

    expect($result)->not->toBeNull();
    expect($result->site_type_id)->toBe($type->id);
});

test('manual classification is not overridden by rules', function () {
    $h = createUserWithOrg();
    $type1 = SiteType::create(['slug' => 'marketplace', 'name' => 'Marketplace', 'color' => '#8B5CF6']);
    $type2 = SiteType::create(['slug' => 'ecommerce', 'name' => 'Store', 'color' => '#3B82F6']);

    DomainClassification::create([
        'domain' => 'shop.ru',
        'site_type_id' => $type1->id,
        'classified_by' => 'manual',
        'organization_id' => $h['org']->id,
    ]);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_contains',
        'pattern' => 'shop',
        'site_type_id' => $type2->id,
        'priority' => 10,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('shop.ru', $h['org']->id);

    expect($result->site_type_id)->toBe($type1->id);
});

test('higher priority rule wins', function () {
    $h = createUserWithOrg();
    $type1 = SiteType::create(['slug' => 'info', 'name' => 'Info', 'color' => '#10B981']);
    $type2 = SiteType::create(['slug' => 'media', 'name' => 'Media', 'color' => '#F97316']);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_contains',
        'pattern' => '.ru',
        'site_type_id' => $type1->id,
        'priority' => 1,
    ]);

    ClassificationRule::create([
        'organization_id' => $h['org']->id,
        'rule_type' => 'domain_exact',
        'pattern' => 'rbc.ru',
        'site_type_id' => $type2->id,
        'priority' => 10,
    ]);

    $service = app(ClassificationService::class);
    $result = $service->classify('rbc.ru', $h['org']->id);

    expect($result->site_type_id)->toBe($type2->id);
});
