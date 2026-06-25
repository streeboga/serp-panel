<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The page-matching listener (PageSerpMatchRepository::upsertForSnapshot) upserts
 * with ON CONFLICT (snapshot_id, serp_result_id) and lists page_id in the DO UPDATE
 * set, so page_id cannot be part of the conflict key. The original migration created
 * a unique index on (page_id, serp_result_id), which never matched the upsert target
 * — every match attempt failed with SQLSTATE 42P10 ("no unique or exclusion constraint
 * matching the ON CONFLICT specification"). This realigns the unique index with the code.
 *
 * Raw SQL with IF [NOT] EXISTS so it is idempotent whether or not the index was already
 * swapped manually on a server.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE page_serp_matches DROP CONSTRAINT IF EXISTS page_serp_matches_page_id_serp_result_id_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS page_serp_matches_snapshot_id_serp_result_id_unique ON page_serp_matches (snapshot_id, serp_result_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS page_serp_matches_snapshot_id_serp_result_id_unique');
        DB::statement('ALTER TABLE page_serp_matches ADD CONSTRAINT page_serp_matches_page_id_serp_result_id_unique UNIQUE (page_id, serp_result_id)');
    }
};
