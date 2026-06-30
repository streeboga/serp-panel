<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectWordstatJob;
use App\Models\WordstatSchedule;
use App\Services\Wordstat\WordstatScheduleService;
use Illuminate\Console\Command;

class CheckWordstatSchedulesCommand extends Command
{
    protected $signature = 'wordstat:check';

    protected $description = 'Dispatch a full Wordstat collection for every due schedule (manual/on-demand)';

    public function handle(WordstatScheduleService $service): int
    {
        $schedules = WordstatSchedule::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $dispatched = 0;

        foreach ($schedules as $schedule) {
            foreach ($service->keywordsFor($schedule) as $keyword) {
                CollectWordstatJob::dispatch(
                    $keyword->id,
                    $schedule->id,
                    $service->regionsFor($schedule, $keyword),
                    $schedule->collect_trends,
                    $schedule->collect_suggestions,
                );
                $dispatched++;
            }

            // Advance the schedule so the per-15-min cron does not re-dispatch
            // every keyword on every tick while the schedule stays "due".
            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addDays($schedule->frequency_days),
            ]);
        }

        $this->info("Dispatched {$dispatched} wordstat jobs.");

        return self::SUCCESS;
    }
}
