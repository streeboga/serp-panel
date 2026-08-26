<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_audits', function (Blueprint $table) {
            // Категории лежат в groups; здесь — выбор отдельных проверок по коду,
            // чтобы можно было выключить, скажем, только релевантность.
            $table->jsonb('check_codes')->nullable()->after('groups');
        });
    }

    public function down(): void
    {
        Schema::table('site_audits', function (Blueprint $table) {
            $table->dropColumn('check_codes');
        });
    }
};
