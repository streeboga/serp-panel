<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Откуда страница попала в прогон. Нужно проверкам «noindex в карте сайта»
 * и «неканонический адрес в карте»: карта обещает поисковику одно, а страница
 * говорит другое — и это видно только при сопоставлении двух источников.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_audit_results', function (Blueprint $table): void {
            $table->boolean('in_sitemap')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('page_audit_results', function (Blueprint $table): void {
            $table->dropColumn('in_sitemap');
        });
    }
};
