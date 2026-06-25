<?php

declare(strict_types=1);

use App\Console\Commands\CheckWordstatSchedulesCommand;
use App\Jobs\CollectWordstatJob;
use App\Models\Keyword;
use App\Models\WordstatSchedule;
use Illuminate\Support\Facades\Queue;

covers(CheckWordstatSchedulesCommand::class);

test('wordstat:check advances next_run_at so it does not re-dispatch every tick', function () {
    Queue::fake();

    $s = createFullStack();

    Keyword::create([
        'cluster_id' => $s['cluster']->id,
        'keyword' => 'купить квартиру',
        'engine' => 'yandex',
        'device' => 'desktop',
        'region_id' => $s['region']->id,
    ]);

    $schedule = WordstatSchedule::create([
        'project_id' => $s['project']->id,
        'frequency_days' => 7,
        'collect_trends' => true,
        'collect_suggestions' => false,
        'regions' => [225],
        'is_active' => true,
        'adapter_type' => 'yandex',
        'next_run_at' => null, // due now
    ]);

    $this->artisan('wordstat:check')->assertSuccessful();

    Queue::assertPushed(CollectWordstatJob::class, 1);

    $schedule->refresh();
    expect($schedule->next_run_at)->not->toBeNull()
        ->and($schedule->next_run_at->isFuture())->toBeTrue()
        ->and($schedule->last_run_at)->not->toBeNull();

    // A second tick must NOT re-dispatch while the schedule is no longer due.
    $this->artisan('wordstat:check')->assertSuccessful();
    Queue::assertPushed(CollectWordstatJob::class, 1);
});
