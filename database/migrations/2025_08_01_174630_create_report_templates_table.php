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
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('report_type')->default('documents');
            $table->json('configuration');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'company_id']);
            $table->index(['is_public', 'is_favorite']);
            $table->index('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
