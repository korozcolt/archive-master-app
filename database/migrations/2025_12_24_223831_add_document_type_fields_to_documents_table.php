<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos para diferenciar si el documento es original o copia,
     * tanto en su versión digital como en su versión física.
     *
     * Casos de uso:
     * - Digital: original (archivo original subido) vs copia (scan/foto del original)
     * - Físico: original (documento físico real) vs copia (fotocopia del original) vs no_aplica (no hay físico)
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Tipo de documento DIGITAL
            $table->enum('digital_document_type', ['original', 'copia'])
                  ->default('copia')
                  ->after('qrcode')
                  ->comment('Indica si el archivo digital es el original o una copia');

            // Tipo de documento FÍSICO
            $table->enum('physical_document_type', ['original', 'copia', 'no_aplica'])
                  ->nullable()
                  ->after('digital_document_type')
                  ->comment('Indica si el documento físico guardado es original, copia o si no existe físico');

            // Índices para filtros y reportes
            $table->index('digital_document_type');
            $table->index('physical_document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['digital_document_type']);
            $table->dropIndex(['physical_document_type']);
            $table->dropColumn(['digital_document_type', 'physical_document_type']);
        });
    }
};
