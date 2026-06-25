<?php

declare(strict_types=1);

use App\Console\Commands\CheckSchedulesCommand;
use App\Models\Keyword;
use App\Models\ScrapeJob;
use App\Models\Scraper;
use App\Models\ScrapeSchedule;

covers(CheckSchedulesCommand::class);

test('routes each keyword to a scraper that supports its engine', function () {
    $s = createFullStack();
    $org = $s['org'];

    $xmlriver = Scraper::create([
        'organization_id' => $org->id,
        'type' => 'xmlriver',
        'name' => 'XMLRiver',
        'base_url' => 'https://xmlriver.com/api',
        'supported_engines' => ['google', 'yandex'],
        'is_active' => true,
    ]);

    $cloud = Scraper::create([
        'organization_id' => $org->id,
        'type' => 'yandex_cloud',
        'name' => 'Yandex Cloud',
        'base_url' => 'https://searchapi.api.cloud.yandex.net/v2/web/searchAsync',
        'supported_engines' => ['yandex'],
        'is_active' => true,
    ]);

    $yandexKw = Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'купить квартиру',
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $s['region']->id,
    ]);

    $googleKw = Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'buy apartment',
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $s['region']->id,
    ]);

    // Schedule points at the Yandex-only Cloud scraper.
    ScrapeSchedule::create([
        'scraper_id' => $cloud->id,
        'project_id' => $s['project']->id,
        'is_active' => true,
        'frequency_days' => 1,
    ]);

    $this->artisan('schedules:check')->assertSuccessful();

    // Yandex keyword stays on the schedule's scraper (Cloud supports yandex).
    expect(ScrapeJob::where('keyword_id', $yandexKw->id)->value('scraper_id'))->toBe($cloud->id);

    // Google keyword is rerouted to XMLRiver since Cloud cannot do Google.
    expect(ScrapeJob::where('keyword_id', $googleKw->id)->value('scraper_id'))->toBe($xmlriver->id);
});
