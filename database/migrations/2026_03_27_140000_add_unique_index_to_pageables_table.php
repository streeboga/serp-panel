<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove existing duplicates before adding unique constraint
        DB::statement('
            DELETE FROM pageables a USING pageables b
            WHERE a.id > b.id
              AND a.page_id = b.page_id
              AND a.pageable_type = b.pageable_type
              AND a.pageable_id = b.pageable_id
        ');

        Schema::table('pageables', function (Blueprint $table) {
            $table->unique(['page_id', 'pageable_type', 'pageable_id'], 'pageables_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pageables', function (Blueprint $table) {
            $table->dropUnique('pageables_unique');
        });
    }
};
