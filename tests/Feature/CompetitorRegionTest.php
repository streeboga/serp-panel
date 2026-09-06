<?php

declare(strict_types=1);

use App\Models\Keyword;
use App\Models\Region;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\CompetitorService;

covers(CompetitorService::class);

test('федеральный конкурент отличается от регионального', function () {
    $h = createFullStack();

    $moscow = $h['region'];
    $spb = Region::create([
        'engine' => 'google', 'code' => 'SPB', 'name' => 'Санкт-Петербург',
        'google_gl' => 'ru', 'google_hl' => 'ru',
    ]);

    // Один ключ в Москве, другой в Петербурге.
    foreach ([[$moscow, 'шины москва'], [$spb, 'шины спб']] as [$region, $phrase]) {
        $keyword = Keyword::create([
            'cluster_id' => $h['cluster']->id,
            'keyword' => $phrase,
            'engine' => 'google',
            'device' => 'desktop',
            'region_id' => $region->id,
        ]);

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $keyword->id,
            'collected_at' => now(),
            'search_engine' => 'google',
            'device' => 'desktop',
            'region_id' => $region->id,
            'total_results' => 100,
        ]);

        // Федеральный виден в обоих регионах, местный — только в своём.
        SerpResult::create([
            'snapshot_id' => $snapshot->id, 'collected_at' => $snapshot->collected_at,
            'position' => 1, 'url' => 'https://federal.example/', 'domain' => 'federal.example',
            'title' => 'Ф', 'result_type' => 'organic', 'is_ads' => false,
        ]);

        SerpResult::create([
            'snapshot_id' => $snapshot->id, 'collected_at' => $snapshot->collected_at,
            'position' => 2, 'url' => "https://{$region->code}.example/", 'domain' => mb_strtolower($region->code).'.example',
            'title' => 'Р', 'result_type' => 'organic', 'is_ads' => false,
        ]);
    }

    $byRegion = collect(app(CompetitorService::class)->getCompetitorsByRegion($h['project']->id))
        ->keyBy('domain');

    expect($byRegion['federal.example']['scope'])->toBe('федеральный')
        ->and($byRegion['federal.example']['regions'])->toHaveCount(2)
        ->and($byRegion['spb.example']['scope'])->toBe('региональный')
        ->and($byRegion['spb.example']['regions'][0]['region'])->toBe('Санкт-Петербург');
});

test('эндпоинт требует project_id и проверяет организацию', function () {
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->getJson('/api/v1/serp/competitors/regions', orgHeaders($h['org']))
        ->assertStatus(422);

    $theirs = createFullStack();

    $this->actingAs($h['user'])
        ->getJson("/api/v1/serp/competitors/regions?project_id={$theirs['project']->id}", orgHeaders($h['org']))
        ->assertNotFound();
});
