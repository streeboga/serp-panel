<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AlertController;
use App\Models\Keyword;
use App\Models\PositionAlert;
use App\Services\AlertService;

covers(AlertController::class, AlertService::class);

// === Story 8.1: Position alerts ===

test('can list alerts', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    PositionAlert::create([
        'organization_id' => $h['org']->id,
        'keyword_id' => $kw->id,
        'threshold_position' => 10,
        'direction' => 'drops_below',
        'channel' => 'email',
        'recipient' => 'test@example.com',
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->getJson('/api/v1/alerts', orgHeaders($h['org']));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create alert', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/alerts', [
            'keyword_id' => $kw->id,
            'threshold_position' => 10,
            'direction' => 'drops_below',
            'channel' => 'email',
            'recipient' => 'admin@example.com',
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('position_alerts', [
        'organization_id' => $h['org']->id,
        'keyword_id' => $kw->id,
        'threshold_position' => 10,
    ]);
});

test('can create telegram alert', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $response = $this->actingAs($h['user'])
        ->postJson('/api/v1/alerts', [
            'keyword_id' => $kw->id,
            'threshold_position' => 5,
            'direction' => 'rises_above',
            'channel' => 'telegram',
            'recipient' => '123456789',
        ], orgHeaders($h['org']));

    $response->assertStatus(201);
    $this->assertDatabaseHas('position_alerts', [
        'channel' => 'telegram',
        'recipient' => '123456789',
    ]);
});

test('can update alert', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $alert = PositionAlert::create([
        'organization_id' => $h['org']->id,
        'keyword_id' => $kw->id,
        'threshold_position' => 10,
        'direction' => 'drops_below',
        'channel' => 'email',
        'recipient' => 'test@example.com',
        'is_active' => true,
    ]);

    $response = $this->actingAs($h['user'])
        ->patchJson("/api/v1/alerts/{$alert->id}", [
            'threshold_position' => 5,
            'is_active' => false,
        ], orgHeaders($h['org']));

    $response->assertOk();
    $this->assertDatabaseHas('position_alerts', [
        'id' => $alert->id,
        'threshold_position' => 5,
        'is_active' => false,
    ]);
});

test('can delete alert', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $alert = PositionAlert::create([
        'organization_id' => $h['org']->id,
        'keyword_id' => $kw->id,
        'threshold_position' => 10,
        'direction' => 'drops_below',
        'channel' => 'email',
        'recipient' => 'test@example.com',
    ]);

    $this->actingAs($h['user'])
        ->deleteJson("/api/v1/alerts/{$alert->id}", [], orgHeaders($h['org']))
        ->assertStatus(204);

    $this->assertDatabaseMissing('position_alerts', ['id' => $alert->id]);
});

test('cannot access other org alert', function () {
    $h1 = createFullStack();
    $h2 = createUserWithOrg();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h1['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h1['region']->id,
    ]);

    $alert = PositionAlert::create([
        'organization_id' => $h1['org']->id,
        'keyword_id' => $kw->id,
        'threshold_position' => 10,
        'direction' => 'drops_below',
        'channel' => 'email',
        'recipient' => 'test@example.com',
    ]);

    $this->actingAs($h2['user'])
        ->getJson("/api/v1/alerts/{$alert->id}", orgHeaders($h2['org']))
        ->assertStatus(404);
});

test('alert validation requires valid direction', function () {
    $h = createFullStack();
    $kw = Keyword::create([
        'keyword' => 'test',
        'cluster_id' => $h['cluster']->id,
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $h['region']->id,
    ]);

    $this->actingAs($h['user'])
        ->postJson('/api/v1/alerts', [
            'keyword_id' => $kw->id,
            'threshold_position' => 10,
            'direction' => 'invalid',
            'channel' => 'email',
            'recipient' => 'test@example.com',
        ], orgHeaders($h['org']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['direction']);
});
