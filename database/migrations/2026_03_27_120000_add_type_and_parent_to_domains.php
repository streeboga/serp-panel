<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('type', 32)->default('competitor')->after('is_own');
            $table->foreignId('parent_id')->nullable()->after('type')
                ->constrained('domains')->nullOnDelete();
            $table->integer('indexed_pages_count')->nullable()->after('parent_id');
        });

        // Backfill: set type based on is_own
        DB::table('domains')->where('is_own', true)->update(['type' => 'own']);
        DB::table('domains')->where('is_own', false)->update(['type' => 'competitor']);
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['type', 'parent_id', 'indexed_pages_count']);
        });
    }
};
