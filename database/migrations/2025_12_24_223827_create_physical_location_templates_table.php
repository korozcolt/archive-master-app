<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacena las estructuras/templates de ubicación física
     * que cada compañía puede configurar según sus necesidades.
     * Ejemplo: Edificio > Piso > Sala > Armario > Estante > Caja
     */
    public function up(): void
    {
        Schema::create('physical_location_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // "Estructura Archivo Central"

            // Configuración de niveles jerárquicos en JSON
            // Ejemplo: [
            //   {"order": 1, "name": "Edificio", "code": "ED", "required": true, "icon": "building", "examples": ["Edificio A"]},
            //   {"order": 2, "name": "Piso", "code": "P", "required": true, "icon": "layers", "examples": ["1", "2", "3"]},
            //   ...
            // ]
            $table->json('levels');

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            // Índices para búsqueda rápida
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_location_templates');
    }
};
