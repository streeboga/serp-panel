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
        Schema::create('domain_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->foreignId('site_type_id')->constrained();
            $table->string('classified_by')->default('rule');
            $table->foreignId('rule_id')->nullable()->constrained('classification_rules')->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['domain', 'organization_id']);
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_classifications');
    }
};
