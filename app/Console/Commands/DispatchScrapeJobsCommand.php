<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeSerpJob;
use App\Models\ScrapeJob;
use App\Models\Scraper;
use Illuminate\Console\Command;

class DispatchScrapeJobsCommand extends Command
{
    protected $signature = 'scrape-jobs:dispatch';

    protected $description = 'Dispatch pending scrape jobs respecting rate limits';

    public function handle(): int
    {
        $scrapers = Scraper::where('is_active', true)->get();
        $dispatched = 0;

        foreach ($scrapers as $scraper) {
            $jobs = ScrapeJob::where('scraper_id', $scraper->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit($scraper->rate_limit)
                ->get();

            foreach ($jobs as $job) {
                ScrapeSerpJob::dispatch($job->id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} scrape jobs.");

        return self::SUCCESS;
    }
}
