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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->json('events');
            $table->string('name')->nullable();
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('retry_attempts')->default(3);
            $table->integer('timeout')->default(30);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Índices para mejorar performance
            $table->index(['company_id', 'active']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
