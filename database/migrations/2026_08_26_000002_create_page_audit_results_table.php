<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_audit_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();

            $table->string('url', 2048);
            // Хэш URL: в Postgres btree не индексирует строки длиннее ~2700 байт.
            $table->char('url_hash', 40);
            $table->string('path', 1024)->default('/');

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->jsonb('redirect_chain')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('html_size')->nullable();

            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedInteger('issues_critical')->default(0);
            $table->unsignedInteger('issues_warning')->default(0);
            $table->unsignedInteger('issues_notice')->default(0);

            $table->jsonb('findings')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['site_audit_id', 'url_hash']);
            $table->index(['page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_audit_results');
    }
};
