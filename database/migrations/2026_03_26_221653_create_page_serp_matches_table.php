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
        Schema::create('page_serp_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedBigInteger('serp_result_id');
            $table->unsignedBigInteger('snapshot_id');
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->integer('position');
            $table->date('collected_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['page_id', 'serp_result_id']);
            $table->index(['page_id', 'keyword_id', 'collected_at']);
            $table->index('snapshot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_serp_matches');
    }
};
