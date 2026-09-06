<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заглушённые коды находок на уровне проекта.
 *
 * Зачем. Прогон по eq.team отдавал 233 warning и 1369 notice, настоящих из них
 * меньше десяти. Остальное — находки, ложные для этого сайта по построению:
 * счётчик Метрики подключается после ответа на cookie-баннер, поэтому
 * http.analytics.missing приходит на каждой странице. Список таких кодов жил
 * во внешнем файле у потребителя API, панель про него не знала, и разговор о
 * качестве сайта каждый раз начинался с разбора, что здесь настоящее.
 *
 * Хранение. `projects.muted_codes` — объект «код находки → причина». Причина
 * обязательна: заглушка без причины через месяц превращается в дыру, о которой
 * никто не помнит. `site_audits.muted_codes` — снимок политики на момент
 * запуска: прогон должен читаться и после того, как политику поменяли.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->jsonb('muted_codes')->nullable()->after('description');
        });

        Schema::table('site_audits', function (Blueprint $table): void {
            $table->jsonb('muted_codes')->nullable()->after('check_codes');
            $table->unsignedInteger('issues_muted')->default(0)->after('issues_notice');
        });

        Schema::table('page_audit_results', function (Blueprint $table): void {
            $table->unsignedInteger('issues_muted')->default(0)->after('issues_notice');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('muted_codes');
        });

        Schema::table('site_audits', function (Blueprint $table): void {
            $table->dropColumn(['muted_codes', 'issues_muted']);
        });

        Schema::table('page_audit_results', function (Blueprint $table): void {
            $table->dropColumn('issues_muted');
        });
    }
};
