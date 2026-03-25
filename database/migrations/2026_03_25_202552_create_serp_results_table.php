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
            CREATE TABLE serp_results (
                id BIGSERIAL,
                snapshot_id BIGINT NOT NULL,
                collected_at DATE NOT NULL,
                position SMALLINT NOT NULL,
                url TEXT NOT NULL,
                domain VARCHAR(255) NOT NULL,
                title TEXT,
                description TEXT,
                snippet_type VARCHAR(50) DEFAULT 'organic',
                is_ads BOOLEAN DEFAULT FALSE,
                cached_page_url TEXT,
                PRIMARY KEY (id, collected_at)
            ) PARTITION BY RANGE (collected_at)
        ");
        DB::statement('CREATE INDEX idx_serp_results_snapshot ON serp_results (snapshot_id, collected_at)');
        DB::statement('CREATE INDEX idx_serp_results_domain ON serp_results (domain, collected_at)');
        DB::statement('CREATE INDEX idx_serp_results_position ON serp_results (position, collected_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS serp_results CASCADE');
    }
};
