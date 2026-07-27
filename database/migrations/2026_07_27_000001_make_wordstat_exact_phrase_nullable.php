<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phrase and exact volumes need dedicated Wordstat queries. Until they are
 * measured they are unknown, not zero — and previously they were stored as
 * broad*0.6 / broad*0.3, which reads as real data. Allow NULL so "not measured"
 * is distinguishable, and clear the derived values already stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE wordstat_frequencies ALTER COLUMN frequency_exact DROP NOT NULL, ALTER COLUMN frequency_exact DROP DEFAULT');
        DB::statement('ALTER TABLE wordstat_frequencies ALTER COLUMN frequency_phrase DROP NOT NULL, ALTER COLUMN frequency_phrase DROP DEFAULT');
        DB::statement('UPDATE wordstat_frequencies SET frequency_exact = NULL, frequency_phrase = NULL WHERE frequency_broad > 0 AND frequency_exact = round(frequency_broad * 0.3) AND frequency_phrase = round(frequency_broad * 0.6)');
    }

    public function down(): void
    {
        DB::statement('UPDATE wordstat_frequencies SET frequency_exact = 0 WHERE frequency_exact IS NULL');
        DB::statement('UPDATE wordstat_frequencies SET frequency_phrase = 0 WHERE frequency_phrase IS NULL');
        DB::statement('ALTER TABLE wordstat_frequencies ALTER COLUMN frequency_exact SET DEFAULT 0, ALTER COLUMN frequency_exact SET NOT NULL');
        DB::statement('ALTER TABLE wordstat_frequencies ALTER COLUMN frequency_phrase SET DEFAULT 0, ALTER COLUMN frequency_phrase SET NOT NULL');
    }
};
