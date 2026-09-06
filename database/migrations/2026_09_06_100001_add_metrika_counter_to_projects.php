<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Счётчик Метрики принадлежит проекту, а токен — организации:
            // один доступ обслуживает несколько сайтов.
            $table->unsignedBigInteger('metrika_counter_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('metrika_counter_id');
        });
    }
};
