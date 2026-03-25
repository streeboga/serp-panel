<?php

namespace App\Console\Commands;

use App\Models\ScrapeJob;
use Illuminate\Console\Command;

class CleanupRawResponsesCommand extends Command
{
    protected $signature = 'cleanup:raw-responses {--days=7}';

    protected $description = 'Clean up raw responses from old scrape jobs';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $updated = ScrapeJob::whereNotNull('raw_response')
            ->where('created_at', '<', now()->subDays($days))
            ->update(['raw_response' => null]);

        $this->info("Cleaned {$updated} raw responses.");

        return self::SUCCESS;
    }
}
