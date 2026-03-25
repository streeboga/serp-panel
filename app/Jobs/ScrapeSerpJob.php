<?php

namespace App\Jobs;

use App\Models\ScrapeJob;
use App\Services\SerpSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScrapeSerpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $scrapeJobId,
    ) {
        $this->onQueue('serp-scrape');
    }

    public function handle(SerpSnapshotService $service): void
    {
        $job = ScrapeJob::findOrFail($this->scrapeJobId);
        $job->update(['status' => 'running', 'started_at' => now(), 'attempts' => $job->attempts + 1]);

        try {
            $service->scrape($job);
        } catch (\Exception $e) {
            $job->update([
                'status' => $job->attempts >= $this->tries ? 'failed' : 'retrying',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
