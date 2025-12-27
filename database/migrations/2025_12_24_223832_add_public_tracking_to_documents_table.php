<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega sistema de tracking público para clientes externos.
     * Permite que personas sin cuenta puedan rastrear el estado de sus documentos
     * mediante un código único y seguro que no expone información interna.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Código único para tracking público (diferente al document_number interno)
            $table->string('public_tracking_code', 32)
                  ->nullable()
                  ->unique()
                  ->after('qrcode')
                  ->comment('Código hash único para tracking público sin autenticación');

            // Control de activación del tracking
            $table->boolean('tracking_enabled')
                  ->default(false)
                  ->after('public_tracking_code')
                  ->comment('Define si el tracking público está activo para este documento');

            // Fecha de expiración del tracking (opcional)
            $table->timestamp('tracking_expires_at')
                  ->nullable()
                  ->after('tracking_enabled')
                  ->comment('Fecha de expiración del código de tracking (opcional)');

            // Índices para búsqueda rápida por código de tracking
            $table->index(['public_tracking_code', 'tracking_enabled']);
            $table->index('tracking_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['public_tracking_code', 'tracking_enabled']);
            $table->dropIndex(['tracking_expires_at']);
            $table->dropColumn(['public_tracking_code', 'tracking_enabled', 'tracking_expires_at']);
        });
    }
};
