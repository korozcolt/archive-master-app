<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sistema de plantillas de documentos que permite a las empresas
     * crear configuraciones reutilizables para tipos de documentos comunes.
     * Incluye campos personalizados, configuraciones por defecto y workflows.
     */
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Información básica de la plantilla
            $table->string('name'); // "Contrato de Servicio", "Factura Comercial", etc.
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icono de Heroicons
            $table->string('color', 20)->default('gray'); // Color de identificación

            // Configuraciones por defecto
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('default_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('default_workflow_id')->nullable()->constrained('workflow_definitions')->nullOnDelete();
            $table->string('default_priority')->default('medium'); // low, medium, high, urgent
            $table->boolean('default_is_confidential')->default(false);
            $table->boolean('default_tracking_enabled')->default(false);

            // Campos personalizados en JSON
            // Ejemplo: [
            //   {"name": "numero_contrato", "label": "Número de Contrato", "type": "text", "required": true},
            //   {"name": "monto", "label": "Monto", "type": "number", "required": true},
            //   {"name": "fecha_vencimiento", "label": "Fecha de Vencimiento", "type": "date", "required": false}
            // ]
            $table->json('custom_fields')->nullable();

            // Configuraciones de validación
            $table->json('required_fields')->nullable(); // ["title", "description", "file", ...]
            $table->json('allowed_file_types')->nullable(); // ["pdf", "docx", "xlsx", ...]
            $table->integer('max_file_size_mb')->nullable(); // Tamaño máximo en MB

            // Configuraciones de etiquetas y categorización
            $table->json('default_tags')->nullable(); // IDs de tags por defecto
            $table->json('suggested_tags')->nullable(); // IDs de tags sugeridos

            // Configuraciones de ubicación
            $table->foreignId('default_physical_location_id')->nullable()->constrained('physical_locations')->nullOnDelete();

            // Prefijo de numeración personalizado
            $table->string('document_number_prefix', 10)->nullable(); // "CONT-", "FACT-", etc.

            // Instrucciones y ayuda
            $table->text('instructions')->nullable(); // Instrucciones para llenar
            $table->text('help_text')->nullable(); // Texto de ayuda

            // Estado y estadísticas
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0); // Cuántas veces se ha usado
            $table->timestamp('last_used_at')->nullable();

            // Auditoría
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'usage_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
