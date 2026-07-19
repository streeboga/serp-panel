<?php

declare(strict_types=1);

use App\Jobs\CollectWordstatJob;
use App\Models\ConnectedAccount;
use App\Models\Keyword;
use App\Models\Region;
use App\Models\WordstatSchedule;
use Illuminate\Support\Facades\Http;

covers(CollectWordstatJob::class);

test('CollectWordstatJob calls the API with yandex_lr but stores the region PK', function () {
    $s = createFullStack();

    // Region whose PK (id) differs from its Yandex lr geo code.
    $region = Region::create([
        'engine' => 'yandex',
        'code' => 'RU',
        'name' => 'Россия',
        'yandex_lr' => 225,
    ]);
    expect($region->id)->not->toBe(225);

    ConnectedAccount::create([
        'organization_id' => $s['org']->id,
        'type' => 'yandex',
        'label' => 'YC',
        'credentials' => ['api_key' => 'k', 'folder_id' => 'f'],
        'is_active' => true,
    ]);

    $keyword = Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'купить квартиру',
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $region->id,
    ]);

    $schedule = WordstatSchedule::create([
        'project_id' => $s['project']->id,
        'frequency_days' => 7,
        'collect_trends' => false,
        'collect_suggestions' => false,
        'regions' => [$region->id],
        'is_active' => true,
        'adapter_type' => 'yandex',
    ]);

    Http::fake([
        '*/topRequests' => Http::response(['totalCount' => 1000, 'results' => [], 'associations' => []], 200),
        '*' => Http::response([], 200),
    ]);

    CollectWordstatJob::dispatchSync($keyword->id, $schedule->id, [$region->id], false, false);

    // FK-safe: stored against the regions PK, not the lr — and the API saw the lr.
    $this->assertDatabaseHas('wordstat_frequencies', [
        'keyword_id' => $keyword->id,
        'region_id' => $region->id,
        'frequency_broad' => 1000,
    ]);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/topRequests')
        && in_array('225', (array) data_get($req->data(), 'regions', []), true));
});

test('CollectWordstatJob stores suggestions with a valid enum type', function () {
    $s = createFullStack();
    $region = Region::create(['engine' => 'yandex', 'code' => 'RU', 'name' => 'РФ', 'yandex_lr' => 225]);
    ConnectedAccount::create([
        'organization_id' => $s['org']->id, 'type' => 'yandex', 'label' => 'YC',
        'credentials' => ['api_key' => 'k', 'folder_id' => 'f'], 'is_active' => true,
    ]);
    $kw = Keyword::create([
        'cluster_id' => $s['cluster']->id, 'keyword' => 'купить квартиру',
        'engine' => 'yandex', 'device' => 'desktop', 'region_id' => $region->id,
    ]);
    $sch = WordstatSchedule::create([
        'project_id' => $s['project']->id, 'frequency_days' => 7,
        'collect_trends' => false, 'collect_suggestions' => true,
        'regions' => [$region->id], 'is_active' => true, 'adapter_type' => 'yandex',
    ]);

    Http::fake([
        '*/topRequests' => Http::response(['totalCount' => 500, 'results' => [['phrase' => 'снять квартиру', 'count' => 300]], 'associations' => []], 200),
        '*' => Http::response([], 200),
    ]);

    // Would throw ValueError on the enum cast if the adapter emits an unknown type.
    CollectWordstatJob::dispatchSync($kw->id, $sch->id, [$region->id], false, true);

    $this->assertDatabaseHas('wordstat_suggestions', ['keyword_id' => $kw->id, 'suggestion' => 'снять квартиру', 'type' => 'suggestion']);
});
