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
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('from_status_id')->constrained('statuses')->onDelete('cascade');
            $table->foreignId('to_status_id')->constrained('statuses')->onDelete('cascade');
            $table->json('roles_allowed')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('requires_comment')->default(false);
            $table->integer('sla_hours')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Usando un nombre más corto para el índice
            $table->unique(['company_id', 'from_status_id', 'to_status_id'], 'workflow_def_transition_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
    }
};
