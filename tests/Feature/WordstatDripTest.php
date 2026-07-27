<?php

declare(strict_types=1);

use App\Console\Commands\DispatchWordstatDripCommand;
use App\Jobs\CollectWordstatJob;
use App\Models\Keyword;
use App\Models\WordstatFrequency;
use App\Models\WordstatSchedule;
use Illuminate\Support\Facades\Queue;

covers(DispatchWordstatDripCommand::class);

test('wordstat:drip collects stale phrases and skips ones still within frequency_days', function () {
    Queue::fake();

    $s = createFullStack();
    $region = $s['region'];

    $make = fn (string $phrase) => Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => $phrase,
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $region->id,
    ]);

    $never = $make('никогда не собирали');
    $old = $make('собрали давно');
    $fresh = $make('собрали сегодня');

    WordstatFrequency::create([
        'keyword_id' => $old->id, 'region_id' => $region->id,
        'frequency_exact' => 1, 'frequency_broad' => 1, 'frequency_phrase' => 1,
        'collected_at' => now()->subDays(10)->toDateString(),
    ]);
    WordstatFrequency::create([
        'keyword_id' => $fresh->id, 'region_id' => $region->id,
        'frequency_exact' => 1, 'frequency_broad' => 1, 'frequency_phrase' => 1,
        'collected_at' => now()->toDateString(),
    ]);

    WordstatSchedule::create([
        'project_id' => $s['project']->id,
        'frequency_days' => 7,
        'collect_trends' => false,
        'collect_suggestions' => false,
        'regions' => [$region->id],
        'is_active' => true,
        'adapter_type' => 'yandex',
    ]);

    $this->artisan('wordstat:drip', ['--limit' => 11])->assertSuccessful();

    // never + old are stale (10d > 7d); fresh (0d) is skipped.
    Queue::assertPushed(CollectWordstatJob::class, 2);
    Queue::assertPushed(CollectWordstatJob::class, fn (CollectWordstatJob $j) => $j->keywordId === $never->id);
    Queue::assertPushed(CollectWordstatJob::class, fn (CollectWordstatJob $j) => $j->keywordId === $old->id);
    Queue::assertNotPushed(CollectWordstatJob::class, fn (CollectWordstatJob $j) => $j->keywordId === $fresh->id);

    // A second run must not re-dispatch phrases already in flight.
    $this->artisan('wordstat:drip', ['--limit' => 11])->assertSuccessful();
    Queue::assertPushed(CollectWordstatJob::class, 2);
});

test('wordstat:drip respects the per-run limit, never-collected first', function () {
    Queue::fake();

    $s = createFullStack();

    foreach (['a', 'b', 'c'] as $phrase) {
        Keyword::create([
            'cluster_id' => $s['cluster']->id,
            'keyword' => $phrase,
            'engine' => 'yandex',
            'device' => 'desktop',
            'region_id' => $s['region']->id,
        ]);
    }

    WordstatSchedule::create([
        'project_id' => $s['project']->id,
        'frequency_days' => 7,
        'collect_trends' => false,
        'collect_suggestions' => false,
        'regions' => [$s['region']->id],
        'is_active' => true,
        'adapter_type' => 'yandex',
    ]);

    $this->artisan('wordstat:drip', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(CollectWordstatJob::class, 2);
});

test('wordstat:drip shares the batch across schedules (a big project cannot starve a small one)', function () {
    Queue::fake();

    $mkProject = function (int $keywordCount): array {
        $s = createFullStack();
        $ids = [];
        for ($i = 0; $i < $keywordCount; $i++) {
            $ids[] = Keyword::create([
                'cluster_id' => $s['cluster']->id,
                'keyword' => "kw-{$s['project']->id}-{$i}",
                'engine' => 'yandex',
                'device' => 'desktop',
                'region_id' => $s['region']->id,
            ])->id;
        }
        WordstatSchedule::create([
            'project_id' => $s['project']->id, 'frequency_days' => 7,
            'collect_trends' => false, 'collect_suggestions' => false,
            'regions' => [$s['region']->id], 'is_active' => true, 'adapter_type' => 'yandex',
        ]);

        return $ids;
    };

    $big = $mkProject(20);
    $small = $mkProject(2);

    // Limit smaller than the big project alone — round-robin must still reach the small one.
    $this->artisan('wordstat:drip', ['--limit' => 6])->assertSuccessful();

    Queue::assertPushed(CollectWordstatJob::class, fn (CollectWordstatJob $j) => in_array($j->keywordId, $small, true));
});

test('with precise collection on, phrases lacking exact/phrase volume count as stale', function () {
    config(['serp.wordstat_precise' => true]);
    Queue::fake();

    $s = createFullStack();
    $kw = Keyword::create([
        'cluster_id' => $s['cluster']->id, 'keyword' => 'поддержка laravel',
        'engine' => 'yandex', 'device' => 'desktop', 'region_id' => $s['region']->id,
    ]);
    // Collected today, but broad-only — precise mode still needs to measure it.
    WordstatFrequency::create([
        'keyword_id' => $kw->id, 'region_id' => $s['region']->id,
        'frequency_broad' => 500, 'frequency_exact' => null, 'frequency_phrase' => null,
        'collected_at' => now()->toDateString(),
    ]);
    WordstatSchedule::create([
        'project_id' => $s['project']->id, 'frequency_days' => 7,
        'collect_trends' => false, 'collect_suggestions' => false,
        'regions' => [$s['region']->id], 'is_active' => true, 'adapter_type' => 'yandex',
    ]);

    $this->artisan('wordstat:drip')->assertSuccessful();

    Queue::assertPushed(CollectWordstatJob::class, fn (CollectWordstatJob $j) => $j->keywordId === $kw->id);
});
