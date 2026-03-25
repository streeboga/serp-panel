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
        Schema::create('wordstat_frequencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained();
            $table->integer('frequency_exact')->default(0);
            $table->integer('frequency_broad')->default(0);
            $table->integer('frequency_phrase')->default(0);
            $table->date('collected_at');
            $table->timestamps();
            $table->index(['keyword_id', 'region_id', 'collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordstat_frequencies');
    }
};
