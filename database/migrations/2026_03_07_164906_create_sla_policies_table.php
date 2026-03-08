<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('business_calendar_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('legal_basis')->nullable();
            $table->unsignedSmallInteger('response_term_days');
            $table->json('warning_days')->nullable();
            $table->unsignedSmallInteger('escalation_days')->nullable();
            $table->unsignedSmallInteger('remission_deadline_days')->default(5);
            $table->boolean('requires_subsanation')->default(true);
            $table->boolean('allows_extension')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index('business_calendar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
