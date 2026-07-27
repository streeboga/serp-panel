<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DomainController;
use App\Models\Domain;
use App\Models\SiteType;
use App\Services\DomainService;

covers(DomainController::class, DomainService::class);

test('updating a domain persists type, parent, tags and site type', function () {
    $h = createFullStack();

    $parent = Domain::create(['project_id' => $h['project']->id, 'name' => 'parent.ru', 'is_own' => false, 'type' => 'competitor']);
    $domain = Domain::create(['project_id' => $h['project']->id, 'name' => 'shop.ru', 'is_own' => false, 'type' => 'competitor']);
    $marketplace = SiteType::create(['slug' => 'marketplace', 'name' => 'Маркетплейс', 'color' => '#000', 'sort_order' => 1]);

    $response = $this->actingAs($h['user'])->patchJson("/api/v1/domains/{$domain->id}", [
        'name' => 'shop.ru',
        'type' => 'satellite',
        'is_own' => false,
        'parent_id' => $parent->id,
        'site_type_id' => $marketplace->id,
        'tags' => ['важный'],
    ], orgHeaders($h['org']));

    $response->assertOk();

    $domain->refresh();
    // These were silently dropped before: only name/is_own passed validation.
    expect($domain->type->value)->toBe('satellite')
        ->and($domain->parent_id)->toBe($parent->id)
        ->and($domain->tags->pluck('name')->all())->toContain('важный');

    $this->assertDatabaseHas('domain_classifications', [
        'domain' => 'shop.ru',
        'organization_id' => $h['org']->id,
        'site_type_id' => $marketplace->id,
        'classified_by' => 'manual',
    ]);
});

test('domain list exposes its site type', function () {
    $h = createFullStack();

    $domain = Domain::create(['project_id' => $h['project']->id, 'name' => 'info.ru', 'is_own' => false, 'type' => 'competitor']);
    $info = SiteType::create(['slug' => 'info', 'name' => 'Инфосайт', 'color' => '#111', 'sort_order' => 2]);

    $this->actingAs($h['user'])->patchJson("/api/v1/domains/{$domain->id}", [
        'site_type_id' => $info->id,
    ], orgHeaders($h['org']))->assertOk();

    $list = $this->actingAs($h['user'])
        ->getJson("/api/v1/projects/{$h['project']->id}/domains", orgHeaders($h['org']))
        ->json('data');

    // JSON:API nests fields under attributes.
    $row = collect($list)->firstWhere('attributes.name', 'info.ru');
    expect($row['attributes']['site_type'])->toBe('Инфосайт');
});
