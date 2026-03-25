<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wordstat_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cluster_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->cascadeOnDelete();
            $table->integer('frequency_days')->default(30);
            $table->boolean('collect_trends')->default(true);
            $table->boolean('collect_suggestions')->default(true);
            $table->jsonb('regions')->default('[]');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordstat_schedules');
    }
};
