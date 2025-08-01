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
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('report_config'); // Configuración del reporte (tipo, filtros, columnas, etc.)
            $table->enum('schedule_frequency', ['daily', 'weekly', 'monthly', 'quarterly']);
            $table->time('schedule_time'); // Hora de ejecución
            $table->tinyInteger('schedule_day_of_week')->nullable(); // Para reportes semanales (0=domingo, 6=sábado)
            $table->tinyInteger('schedule_day_of_month')->nullable(); // Para reportes mensuales (1-31)
            $table->json('email_recipients'); // Lista de emails para envío
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'next_run_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
