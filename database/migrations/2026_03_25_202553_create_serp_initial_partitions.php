<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $startMonth = now()->startOfMonth();
        for ($i = 0; $i < 3; $i++) {
            $from = $startMonth->copy()->addMonths($i)->format('Y-m-d');
            $to = $startMonth->copy()->addMonths($i + 1)->format('Y-m-d');
            $suffix = $startMonth->copy()->addMonths($i)->format('Y_m');
            DB::statement("CREATE TABLE serp_snapshots_{$suffix} PARTITION OF serp_snapshots FOR VALUES FROM ('{$from}') TO ('{$to}')");
            DB::statement("CREATE TABLE serp_results_{$suffix} PARTITION OF serp_results FOR VALUES FROM ('{$from}') TO ('{$to}')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $startMonth = now()->startOfMonth();
        for ($i = 0; $i < 3; $i++) {
            $suffix = $startMonth->copy()->addMonths($i)->format('Y_m');
            DB::statement("DROP TABLE IF EXISTS serp_snapshots_{$suffix}");
            DB::statement("DROP TABLE IF EXISTS serp_results_{$suffix}");
        }
    }
};
