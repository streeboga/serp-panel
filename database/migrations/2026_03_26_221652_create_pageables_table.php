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
        Schema::create('pageables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('pageable_type');
            $table->unsignedBigInteger('pageable_id');
            $table->string('engine', 16)->nullable();
            $table->string('device', 16)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_target')->default(true);
            $table->timestamps();

            $table->index(['pageable_type', 'pageable_id']);
            $table->index('page_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pageables');
    }
};
