<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Привязка внешних ресурсов (Вебмастер, Search Console, Метрика) к нашим доменам.
 *
 * Именно к доменам, а не к проектам: прогон аудита адресует domain_id, и проект
 * с двумя сайтами один счётчик поделить не может. Счётчик Метрики переезжает
 * сюда же — на проде он не заполнен ни у кого, переносить нечего.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            // host_id Вебмастера выглядит как "https:example.com:443", не как домен.
            $table->string('webmaster_host_id')->nullable();
            // siteUrl Search Console: "sc-domain:example.com" либо "https://example.com/".
            $table->string('search_console_site')->nullable();
            $table->unsignedBigInteger('metrika_counter_id')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->text('google_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('metrika_counter_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropColumn(['webmaster_host_id', 'search_console_site', 'metrika_counter_id']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['google_token', 'google_refresh_token', 'google_token_expires_at']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('metrika_counter_id')->nullable();
        });
    }
};
