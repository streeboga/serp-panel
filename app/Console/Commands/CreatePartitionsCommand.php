<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreatePartitionsCommand extends Command
{
    protected $signature = 'partitions:create';

    protected $description = 'Create next month partitions for SERP tables';

    public function handle(): int
    {
        $nextMonth = now()->addMonth()->startOfMonth();
        $from = $nextMonth->format('Y-m-d');
        $to = $nextMonth->copy()->addMonth()->format('Y-m-d');
        $suffix = $nextMonth->format('Y_m');

        foreach (['serp_snapshots', 'serp_results'] as $table) {
            $partitionName = "{$table}_{$suffix}";

            $exists = DB::select('SELECT 1 FROM pg_tables WHERE tablename = ?', [$partitionName]);

            if (empty($exists)) {
                DB::statement("CREATE TABLE {$partitionName} PARTITION OF {$table} FOR VALUES FROM ('{$from}') TO ('{$to}')");
                $this->info("Created partition: {$partitionName}");
            } else {
                $this->info("Partition already exists: {$partitionName}");
            }
        }

        return self::SUCCESS;
    }
}
