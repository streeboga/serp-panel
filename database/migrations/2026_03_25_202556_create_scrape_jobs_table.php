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
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scraper_id')->constrained();
            $table->foreignId('schedule_id')->nullable()->constrained('scrape_schedules')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('engine');
            $table->foreignId('region_id')->constrained();
            $table->string('device')->default('desktop');
            $table->integer('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['scraper_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_jobs');
    }
};
