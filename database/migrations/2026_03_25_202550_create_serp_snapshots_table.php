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
        DB::statement("
            CREATE TABLE serp_snapshots (
                id BIGSERIAL,
                keyword_id BIGINT NOT NULL,
                collected_at DATE NOT NULL,
                search_engine VARCHAR(10) NOT NULL,
                device VARCHAR(10) NOT NULL DEFAULT 'desktop',
                region_id BIGINT NOT NULL,
                total_results INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                PRIMARY KEY (id, collected_at)
            ) PARTITION BY RANGE (collected_at)
        ");
        DB::statement('CREATE INDEX idx_serp_snapshots_keyword ON serp_snapshots (keyword_id, collected_at)');
        DB::statement('CREATE INDEX idx_serp_snapshots_engine ON serp_snapshots (search_engine, collected_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS serp_snapshots CASCADE');
    }
};
