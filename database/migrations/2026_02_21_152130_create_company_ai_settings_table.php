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
        Schema::create('company_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('provider', ['none', 'openai', 'gemini'])->default('none');
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('monthly_budget_cents')->nullable();
            $table->unsignedInteger('daily_doc_limit')->default(100);
            $table->unsignedInteger('max_pages_per_doc')->default(100);
            $table->boolean('store_outputs')->default(true);
            $table->boolean('redact_pii')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_ai_settings');
    }
};
