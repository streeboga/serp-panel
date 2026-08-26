<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_audit_id')->constrained()->cascadeOnDelete();

            $table->string('url', 2048);
            // Ссылка встречается на сотне страниц, а запросить её надо один раз —
            // уникальность по хэшу и есть весь смысл этой таблицы.
            $table->char('url_hash', 40);
            $table->string('type', 16);          // link | image
            $table->boolean('internal')->default(true);

            $table->unsignedInteger('reference_count')->default(1);
            $table->foreignId('first_page_id')->nullable()->constrained('page_audit_results')->nullOnDelete();

            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('content_type', 128)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->unique(['site_audit_id', 'url_hash']);
            $table->index(['site_audit_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_resources');
    }
};
