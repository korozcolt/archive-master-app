<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla registra el historial completo de movimientos físicos de documentos.
     * Permite trazabilidad total: quién movió qué documento, cuándo, desde dónde y hacia dónde.
     */
    public function up(): void
    {
        Schema::create('document_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('physical_location_id')->nullable()->constrained('physical_locations')->nullOnDelete();
            $table->foreignId('moved_from_location_id')->nullable()->constrained('physical_locations')->nullOnDelete();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();

            // Tipo de movimiento
            $table->enum('movement_type', ['stored', 'moved', 'retrieved', 'returned'])->default('stored');
            // stored: Primera vez que se guarda
            // moved: Se mueve de una ubicación a otra
            // retrieved: Se saca del archivo (checkout)
            // returned: Se devuelve al archivo (checkin)

            $table->text('notes')->nullable(); // Razón del movimiento
            $table->timestamp('moved_at'); // Fecha/hora del movimiento

            // Índices para consultas frecuentes
            $table->index(['document_id', 'moved_at']);
            $table->index('physical_location_id');
            $table->index('movement_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_location_history');
    }
};
