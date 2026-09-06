<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Рёбра графа внутренних ссылок. Отдельно от audit_resources: там строка
        // на уникальный адрес и только первая ссылающаяся страница, а графу нужны
        // все связи «откуда → куда».
        Schema::create('audit_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_page_id')->constrained('page_audit_results')->cascadeOnDelete();

            $table->string('to_url', 2048);
            $table->char('to_hash', 40);
            $table->string('anchor', 512)->nullable();
            $table->boolean('nofollow')->default(false);

            $table->index(['site_audit_id', 'to_hash']);
            $table->index(['site_audit_id', 'from_page_id']);
        });

        Schema::table('page_audit_results', function (Blueprint $table) {
            // Кликов от главной. NULL — страница недостижима по ссылкам.
            $table->unsignedSmallInteger('depth')->nullable()->after('path');
            $table->unsignedInteger('inbound_links')->nullable()->after('depth');
        });
    }

    public function down(): void
    {
        Schema::table('page_audit_results', function (Blueprint $table) {
            $table->dropColumn(['depth', 'inbound_links']);
        });

        Schema::dropIfExists('audit_links');
    }
};
