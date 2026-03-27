<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_index_results', function (Blueprint $table) {
            // Rename collected_at → first_seen_at, add last_seen_at
            $table->renameColumn('collected_at', 'first_seen_at');
        });

        Schema::table('domain_index_results', function (Blueprint $table) {
            $table->date('last_seen_at')->nullable()->after('first_seen_at');
            $table->renameColumn('position', 'last_position');
        });

        // Backfill: set last_seen_at = first_seen_at for existing rows
        \Illuminate\Support\Facades\DB::statement('UPDATE domain_index_results SET last_seen_at = first_seen_at');

        Schema::table('domain_index_results', function (Blueprint $table) {
            $table->date('last_seen_at')->nullable(false)->change();

            // Remove old duplicates: keep only the latest per domain+url+engine
            // Done via raw SQL before adding unique constraint
        });

        // Remove duplicates: keep row with max id per (domain_id, url, engine)
        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            DELETE FROM domain_index_results
            WHERE id NOT IN (
                SELECT MAX(id) FROM domain_index_results
                GROUP BY domain_id, url, engine
            )
        SQL);

        // Update first_seen_at to min date for kept rows
        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            UPDATE domain_index_results dir
            SET first_seen_at = COALESCE(
                (SELECT MIN(first_seen_at) FROM domain_index_results d2
                 WHERE d2.domain_id = dir.domain_id AND d2.url = dir.url AND d2.engine = dir.engine),
                dir.first_seen_at
            )
        SQL);

        Schema::table('domain_index_results', function (Blueprint $table) {
            // Drop old index, add unique constraint
            $table->dropIndex(['domain_id', 'engine', 'collected_at']);
            $table->unique(['domain_id', 'url', 'engine'], 'domain_index_results_unique');
        });
    }

    public function down(): void
    {
        Schema::table('domain_index_results', function (Blueprint $table) {
            $table->dropUnique('domain_index_results_unique');
            $table->renameColumn('last_position', 'position');
            $table->renameColumn('first_seen_at', 'collected_at');
            $table->dropColumn('last_seen_at');
            $table->index(['domain_id', 'engine', 'collected_at']);
        });
    }
};
