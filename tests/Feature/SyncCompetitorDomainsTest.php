<?php

declare(strict_types=1);

use App\Console\Commands\SyncCompetitorDomainsCommand;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Page;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;

covers(SyncCompetitorDomainsCommand::class);

/** Rank $domain at $position for each phrase, so it accrues keyword_count. */
function rankDomain(array $h, array $phrases, string $domain, int $position = 5): void
{
    $today = now()->toDateString();

    foreach ($phrases as $phrase) {
        $kw = Keyword::create([
            'keyword' => $phrase, 'cluster_id' => $h['cluster']->id,
            'engine' => 'yandex', 'device' => 'desktop', 'region_id' => $h['region']->id,
        ]);

        $snapshot = SerpSnapshot::create([
            'keyword_id' => $kw->id, 'collected_at' => $today, 'search_engine' => 'yandex',
            'device' => 'desktop', 'region_id' => $h['region']->id, 'total_results' => 100,
        ]);

        SerpResult::insert([[
            'snapshot_id' => $snapshot->id, 'collected_at' => $today, 'position' => $position,
            'url' => "https://{$domain}/".md5($phrase), 'domain' => $domain,
            'title' => 'T', 'description' => '', 'snippet_type' => 'organic', 'is_ads' => false,
        ]]);
    }
}

test('competitors met in the SERP become project domains with their ranking pages', function () {
    $h = createFullStack();

    rankDomain($h, ['фраза один', 'фраза два', 'фраза три'], 'competitor.ru');

    $this->artisan('competitors:sync', ['--min-keywords' => 3])->assertSuccessful();

    $domain = Domain::where('project_id', $h['project']->id)->where('name', 'competitor.ru')->first();

    expect($domain)->not->toBeNull()
        ->and($domain->is_own)->toBeFalse();

    // Its ranking URLs are attached to that domain, so the Pages tab has content.
    expect(Page::where('domain_id', $domain->id)->count())->toBe(3);
});

test('one-off domains stay out until they rank for enough phrases', function () {
    $h = createFullStack();

    rankDomain($h, ['единственная фраза'], 'random.ru');

    $this->artisan('competitors:sync', ['--min-keywords' => 3])->assertSuccessful();

    expect(Domain::where('project_id', $h['project']->id)->where('name', 'random.ru')->exists())->toBeFalse();
});

test('syncing twice does not duplicate domains or pages', function () {
    $h = createFullStack();

    rankDomain($h, ['фраза один', 'фраза два'], 'competitor.ru');

    $this->artisan('competitors:sync', ['--min-keywords' => 2])->assertSuccessful();
    $this->artisan('competitors:sync', ['--min-keywords' => 2])->assertSuccessful();

    expect(Domain::where('project_id', $h['project']->id)->where('name', 'competitor.ru')->count())->toBe(1)
        ->and(Page::where('project_id', $h['project']->id)->count())->toBe(2);
});
