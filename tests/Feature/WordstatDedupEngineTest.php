<?php

declare(strict_types=1);

use App\Console\Commands\CheckWordstatSchedulesCommand;
use App\Jobs\CollectWordstatJob;
use App\Models\Keyword;
use App\Models\WordstatSchedule;
use Illuminate\Support\Facades\Queue;

covers(CheckWordstatSchedulesCommand::class);

test('wordstat collects each phrase once, keeping the yandex row over its google twin', function () {
    Queue::fake();

    $s = createFullStack();

    $yandex = Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'купить квартиру',
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $s['region']->id,
    ]);

    // Same phrase, Google engine — must NOT produce a second wordstat job.
    Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'купить квартиру',
        'engine' => 'google',
        'device' => 'desktop',
        'region_id' => $s['region']->id,
    ]);

    WordstatSchedule::create([
        'project_id' => $s['project']->id,
        'frequency_days' => 7,
        'collect_trends' => false,
        'collect_suggestions' => false,
        'regions' => [$s['region']->id],
        'is_active' => true,
        'adapter_type' => 'yandex',
        'next_run_at' => null,
    ]);

    $this->artisan('wordstat:check')->assertSuccessful();

    // Exactly one job, for the Yandex keyword.
    Queue::assertPushed(CollectWordstatJob::class, 1);
    Queue::assertPushed(CollectWordstatJob::class, fn (CollectWordstatJob $job) => $job->keywordId === $yandex->id);
});
