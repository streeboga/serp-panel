<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();

            $table->string('scope', 16)->default('site');
            $table->string('status', 16)->default('pending');
            $table->string('batch_id')->nullable();

            // Какие группы проверок гоняем. NULL = все включённые в config/audit.php.
            $table->jsonb('groups')->nullable();

            // Вход для scope=url ({url: ...}) и scope=pages ({page_ids: [...]}).
            $table->jsonb('input')->nullable();

            $table->unsignedInteger('pages_total')->default(0);
            $table->unsignedInteger('pages_done')->default(0);

            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedInteger('issues_critical')->default(0);
            $table->unsignedInteger('issues_warning')->default(0);
            $table->unsignedInteger('issues_notice')->default(0);

            // Находки и метрики уровня сайта: robots.txt, sitemap, SSL, 404, редиректы.
            $table->jsonb('findings')->nullable();
            $table->jsonb('metrics')->nullable();

            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audits');
    }
};
