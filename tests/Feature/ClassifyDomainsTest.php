<?php

declare(strict_types=1);

use App\Console\Commands\ClassifyDomainsCommand;
use App\Models\ClassificationRule;
use App\Models\Domain;
use App\Models\DomainClassification;
use App\Models\SiteType;

covers(ClassifyDomainsCommand::class);

test('rules assign a site type to registered domains', function () {
    $h = createFullStack();

    $marketplace = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#000', 'sort_order' => 1]);
    ClassificationRule::create([
        'organization_id' => $h['org']->id, 'rule_type' => 'domain_contains',
        'pattern' => 'ozon.ru', 'site_type_id' => $marketplace->id, 'priority' => 100, 'is_system' => false,
    ]);

    Domain::create(['project_id' => $h['project']->id, 'name' => 'www.ozon.ru', 'is_own' => false, 'type' => 'competitor']);
    Domain::create(['project_id' => $h['project']->id, 'name' => 'unknown.ru', 'is_own' => false, 'type' => 'competitor']);

    $this->artisan('domains:classify')->assertSuccessful();

    $this->assertDatabaseHas('domain_classifications', [
        'domain' => 'www.ozon.ru', 'site_type_id' => $marketplace->id, 'classified_by' => 'rule',
    ]);
    // No rule matched — stays untyped rather than guessing.
    expect(DomainClassification::where('domain', 'unknown.ru')->exists())->toBeFalse();
});

test('a manual site type is not overwritten by rules', function () {
    $h = createFullStack();

    $marketplace = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#000', 'sort_order' => 1]);
    $info = SiteType::create(['slug' => 'info', 'name' => 'Инфосайт', 'color' => '#111', 'sort_order' => 2]);

    ClassificationRule::create([
        'organization_id' => $h['org']->id, 'rule_type' => 'domain_contains',
        'pattern' => 'ozon.ru', 'site_type_id' => $marketplace->id, 'priority' => 100, 'is_system' => false,
    ]);
    Domain::create(['project_id' => $h['project']->id, 'name' => 'www.ozon.ru', 'is_own' => false, 'type' => 'competitor']);
    DomainClassification::create([
        'domain' => 'www.ozon.ru', 'organization_id' => $h['org']->id,
        'site_type_id' => $info->id, 'classified_by' => 'manual',
    ]);

    $this->artisan('domains:classify')->assertSuccessful();

    expect(DomainClassification::where('domain', 'www.ozon.ru')->first()->site_type_id)->toBe($info->id);
});
