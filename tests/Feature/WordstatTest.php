<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\WordstatController;
use App\Http\Controllers\Api\V1\WordstatScheduleController;
use App\Models\Keyword;
use App\Models\WordstatFrequency;
use App\Models\WordstatSchedule;
use App\Models\WordstatSuggestion;
use App\Models\WordstatTrend;
use App\Services\WordstatService;

covers(WordstatController::class, WordstatService::class, WordstatScheduleController::class);

// === Story 5.1: Wordstat API endpoints ===

test('can get wordstat frequencies for keyword', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'seo',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    WordstatFrequency::create([
        'keyword_id' => $kw->id,
        'region_id' => $h['region']->id,
        'frequency_exact' => 100,
        'frequency_broad' => 500,
        'frequency_phrase' => 300,
        'collected_at' => '2026-03-25',
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/wordstat", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can get wordstat trends for keyword', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'seo',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    WordstatTrend::create([
        'keyword_id' => $kw->id,
        'region_id' => $h['region']->id,
        'month' => '2026-01-01',
        'absolute_value' => 1000,
        'collected_at' => '2026-03-25',
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/wordstat/trends?region_id={$h['region']->id}", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can get wordstat suggestions for keyword', function () {
    $h = createFullStack();

    $kw = Keyword::create([
        'keyword' => 'seo',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    WordstatSuggestion::create([
        'keyword_id' => $kw->id,
        'suggestion' => 'seo tools',
        'frequency' => 500,
        'type' => 'suggestion',
        'collected_at' => '2026-03-25',
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson("/api/v1/keywords/{$kw->id}/wordstat/suggestions", orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// === Story 5.2: Wordstat schedules ===

test('can list wordstat schedules', function () {
    $h = createFullStack();

    WordstatSchedule::create([
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
        'collect_trends' => true,
        'collect_suggestions' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/wordstat-schedules', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create wordstat schedule', function () {
    $h = createFullStack();

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/wordstat-schedules', [
            'project_id' => $h['project']->id,
            'frequency_days' => 7,
            'collect_trends' => true,
            'collect_suggestions' => true,
            'is_active' => true,
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('wordstat_schedules', [
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
    ]);
});

test('can update wordstat schedule', function () {
    $h = createFullStack();

    $schedule = WordstatSchedule::create([
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/wordstat-schedules/{$schedule->id}", [
            'frequency_days' => 14,
        ], orgHeaders($h['org']));

    $response->assertOk();
    $this->assertDatabaseHas('wordstat_schedules', [
        'id' => $schedule->id,
        'frequency_days' => 14,
    ]);
});

test('can delete wordstat schedule', function () {
    $h = createFullStack();

    $schedule = WordstatSchedule::create([
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
        'is_active' => true,
    ]);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/wordstat-schedules/{$schedule->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('wordstat_schedules', ['id' => $schedule->id]);
});

test('can trigger wordstat schedule run-now', function () {
    $h = createFullStack();

    $schedule = WordstatSchedule::create([
        'project_id' => $h['project']->id,
        'frequency_days' => 7,
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->postJson("/api/v1/wordstat-schedules/{$schedule->id}/run-now", [], orgHeaders($h['org']));

    $response->assertOk();
});
