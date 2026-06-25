<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\ScrapeJob;
use App\Models\Scraper;
use App\Models\ScrapeSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckSchedulesCommand extends Command
{
    protected $signature = 'schedules:check';

    protected $description = 'Check scrape schedules and create pending jobs';

    public function handle(): int
    {
        $schedules = ScrapeSchedule::with('scraper')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $created = 0;

        foreach ($schedules as $schedule) {
            $keywords = $this->resolveKeywords($schedule);
            $orgScrapers = $this->orgScrapers($schedule);

            foreach ($keywords as $keyword) {
                ScrapeJob::create([
                    'keyword_id' => $keyword->id,
                    'scraper_id' => $this->resolveScraperId($orgScrapers, $schedule->scraper_id, $keyword->engine->value),
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

    /**
     * Active scrapers in the schedule owner's organization, used to route each
     * keyword to a scraper that actually supports its engine.
     *
     * @return Collection<int, Scraper>
     */
    private function orgScrapers(ScrapeSchedule $schedule): Collection
    {
        // scraper_id is a non-nullable FK with cascade delete, so the relation always resolves.
        return Scraper::where('organization_id', $schedule->scraper->organization_id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Pick a scraper for the keyword's engine. The schedule's own scraper wins
     * when it supports the engine; otherwise the first org scraper that does
     * (e.g. a Yandex-only schedule still routes Google keywords to XMLRiver).
     * Falls back to the schedule's scraper when nothing matches.
     *
     * @param  Collection<int, Scraper>  $orgScrapers
     */
    private function resolveScraperId(Collection $orgScrapers, int $defaultScraperId, string $engine): int
    {
        $supporting = $orgScrapers->filter(
            static fn (Scraper $scraper): bool => in_array($engine, $scraper->supported_engines ?? [], true),
        );

        if ($supporting->isEmpty()) {
            return $defaultScraperId;
        }

        $scraper = $supporting->firstWhere('id', $defaultScraperId) ?? $supporting->first();

        return $scraper->id;
    }

    /** @return Collection<int, Keyword> */
    private function resolveKeywords(ScrapeSchedule $schedule): Collection
    {
        if ($schedule->keyword_id) {
            return collect([Keyword::findOrFail($schedule->keyword_id)]);
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
