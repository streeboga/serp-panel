<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_index_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title', 512)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('snippet_links')->nullable();
            $table->integer('position');
            $table->string('engine', 16);
            $table->date('collected_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['domain_id', 'engine', 'collected_at']);
            $table->index(['domain_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_index_results');
    }
};
