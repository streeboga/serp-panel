<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectWordstatJob;
use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\WordstatSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckWordstatSchedulesCommand extends Command
{
    protected $signature = 'wordstat:check';

    protected $description = 'Check wordstat schedules and dispatch collection jobs';

    public function handle(): int
    {
        $schedules = WordstatSchedule::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $dispatched = 0;

        foreach ($schedules as $schedule) {
            $keywords = $this->resolveKeywords($schedule);
            $regionIds = $schedule->regions ?: [];

            foreach ($keywords as $keyword) {
                $regions = empty($regionIds) ? [$keyword->region_id] : $regionIds;

                CollectWordstatJob::dispatch(
                    $keyword->id,
                    $schedule->id,
                    $regions,
                    $schedule->collect_trends,
                    $schedule->collect_suggestions,
                );
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} wordstat jobs.");

        return self::SUCCESS;
    }

    /** @return Collection<int, Keyword> */
    private function resolveKeywords(WordstatSchedule $schedule): Collection
    {
        if ($schedule->keyword_id) {
            return collect([Keyword::findOrFail($schedule->keyword_id)]);
        }
        if ($schedule->cluster_id) {
            return Keyword::where('cluster_id', $schedule->cluster_id)->get();
        }
        if ($schedule->project_id) {
            $domainIds = Domain::where('project_id', $schedule->project_id)->pluck('id');
            $categoryIds = Category::whereIn('domain_id', $domainIds)->pluck('id');
            $clusterIds = Cluster::whereIn('category_id', $categoryIds)->pluck('id');

            return Keyword::whereIn('cluster_id', $clusterIds)->get();
        }

        return collect();
    }
}
