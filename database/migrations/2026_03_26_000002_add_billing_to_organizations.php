<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('billing_tier')->default('free'); // free, starter, pro, enterprise
            $table->integer('max_keywords')->default(100);
            $table->integer('max_projects')->default(3);
            $table->integer('max_scrapers')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['billing_tier', 'max_keywords', 'max_projects', 'max_scrapers']);
        });
    }
};
