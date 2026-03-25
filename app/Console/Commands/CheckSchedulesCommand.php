<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\ScrapeJob;
use App\Models\ScrapeSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckSchedulesCommand extends Command
{
    protected $signature = 'schedules:check';

    protected $description = 'Check scrape schedules and create pending jobs';

    public function handle(): int
    {
        $schedules = ScrapeSchedule::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $created = 0;

        foreach ($schedules as $schedule) {
            $keywords = $this->resolveKeywords($schedule);

            foreach ($keywords as $keyword) {
                ScrapeJob::create([
                    'keyword_id' => $keyword->id,
                    'scraper_id' => $schedule->scraper_id,
                    'schedule_id' => $schedule->id,
                    'status' => 'pending',
                    'engine' => $keyword->engine->value,
                    'region_id' => $keyword->region_id,
                    'device' => $keyword->device->value,
                ]);
                $created++;
            }

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addDays($schedule->frequency_days),
            ]);
        }

        $this->info("Created {$created} scrape jobs.");

        return self::SUCCESS;
    }

    private function resolveKeywords(ScrapeSchedule $schedule): Collection
    {
        if ($schedule->keyword_id) {
            return collect([Keyword::find($schedule->keyword_id)]);
        }

        if ($schedule->cluster_id) {
            return Keyword::where('cluster_id', $schedule->cluster_id)->get();
        }

        if ($schedule->category_id) {
            $clusterIds = Cluster::where('category_id', $schedule->category_id)->pluck('id');

            return Keyword::whereIn('cluster_id', $clusterIds)->get();
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
