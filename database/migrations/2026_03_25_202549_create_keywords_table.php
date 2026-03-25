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
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->string('engine');
            $table->string('device')->default('desktop');
            $table->foreignId('region_id')->constrained();
            $table->timestamps();
            $table->index(['cluster_id', 'engine']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
