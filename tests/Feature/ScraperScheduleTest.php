<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\ScraperController;
use App\Models\Scraper;
use App\Models\ScrapeSchedule;
use App\Services\ScheduleService;
use App\Services\ScraperService;

covers(ScraperController::class, ScheduleController::class, ScraperService::class, ScheduleService::class);

// === Story 7.1: Scrapers CRUD & health check ===

test('can list scrapers', function () {
    $h = createUserWithOrg();
    Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Test Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/scrapers', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create scraper', function () {
    $h = createUserWithOrg();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/scrapers', [
            'type' => 'xmlriver',
            'name' => 'New Scraper',
            'base_url' => 'https://xmlriver.com/api',
            'credentials' => ['api_key' => 'test123'],
            'supported_engines' => ['google', 'yandex'],
            'rate_limit' => 10,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('scrapers', [
        'organization_id' => $h['org']->id,
        'name' => 'New Scraper',
    ]);
});

test('can update scraper', function () {
    $h = createUserWithOrg();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Old Name',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/scrapers/{$scraper->id}", [
            'name' => 'Updated Name',
            'is_active' => false,
        ], orgHeaders($h['org']));

    $response->assertOk();
    $this->assertDatabaseHas('scrapers', [
        'id' => $scraper->id,
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});

test('can delete scraper', function () {
    $h = createUserWithOrg();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'To Delete',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/scrapers/{$scraper->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('scrapers', ['id' => $scraper->id]);
});

test('cannot access scraper from other org', function () {
    $h1 = createUserWithOrg();
    $h2 = createUserWithOrg();

    $scraper = Scraper::create([
        'organization_id' => $h1['org']->id,
        'type' => 'xmlriver',
        'name' => 'Private Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $this->actingAs($h2['user'])
        ->getJson("/api/v1/scrapers/{$scraper->id}", orgHeaders($h2['org']))
        ->assertStatus(404);
});

// === Story 7.2: Schedules CRUD ===

test('can list schedules', function () {
    $h = createFullStack();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    ScrapeSchedule::create([
        'project_id' => $h['project']->id,
        'scraper_id' => $scraper->id,
        'frequency_days' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/schedules', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create schedule', function () {
    $h = createFullStack();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/schedules', [
            'project_id' => $h['project']->id,
            'scraper_id' => $scraper->id,
            'frequency_days' => 7,
            'is_active' => true,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('scrape_schedules', [
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
    ]);
});

test('can update schedule', function () {
    $h = createFullStack();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $schedule = ScrapeSchedule::create([
        'project_id' => $h['project']->id,
        'scraper_id' => $scraper->id,
        'frequency_days' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/schedules/{$schedule->id}", [
            'frequency_days' => 14,
        ], orgHeaders($h['org']));

    $response->assertOk();
    $this->assertDatabaseHas('scrape_schedules', [
        'id' => $schedule->id,
        'frequency_days' => 14,
    ]);
});

test('can delete schedule', function () {
    $h = createFullStack();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $schedule = ScrapeSchedule::create([
        'project_id' => $h['project']->id,
        'scraper_id' => $scraper->id,
        'frequency_days' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/schedules/{$schedule->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('scrape_schedules', ['id' => $schedule->id]);
});

test('can trigger schedule run-now', function () {
    $h = createFullStack();
    $scraper = Scraper::create([
        'organization_id' => $h['org']->id,
        'type' => 'xmlriver',
        'name' => 'Scraper',
        'base_url' => 'https://xmlriver.com/api',
        'is_active' => true,
    ]);

    $schedule = ScrapeSchedule::create([
        'project_id' => $h['project']->id,
        'scraper_id' => $scraper->id,
        'frequency_days' => 7,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->postJson("/api/v1/schedules/{$schedule->id}/run-now", [], orgHeaders($h['org']));

    $response->assertOk();
});
