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
        Schema::create('document_ai_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_ai_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('summary_md')->nullable();
            $table->json('executive_bullets')->nullable();
            $table->json('suggested_tags')->nullable();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('suggested_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->json('entities')->nullable();
            $table->json('confidence')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_ai_outputs');
    }
};
