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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('engine');
            $table->string('code');
            $table->string('name');
            $table->integer('yandex_lr')->nullable();
            $table->string('google_gl')->nullable();
            $table->string('google_hl')->nullable();
            $table->timestamps();
            $table->unique(['engine', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
