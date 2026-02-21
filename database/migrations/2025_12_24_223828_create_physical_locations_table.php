<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacena las ubicaciones físicas concretas donde se guardan
     * los documentos. Cada ubicación sigue la estructura definida en su template.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('physical_locations', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('physical_location_templates')->nullOnDelete();

            // Representaciones de la ubicación
            $table->string('full_path', 500); // "Edificio A / Piso 3 / Archivo / Armario 12 / Estante B / Caja 045"
            $table->string('code', 255)->unique(); // "ED-A/P-3/SALA-ARCH/ARM-12/EST-B/CAJA-045"

            // Datos estructurados para búsqueda granular
            // Ejemplo: {"edificio": "Edificio A", "piso": "3", "sala": "Archivo Central", ...}
            $table->json('structured_data');

            // QR único para cada ubicación física (para imprimir en armarios/estantes)
            $table->string('qr_code', 100)->nullable()->unique();

            // Gestión de capacidad
            $table->integer('capacity_total')->nullable(); // Capacidad total (ej: 100 documentos)
            $table->integer('capacity_used')->default(0); // Documentos actualmente guardados aquí

            // Estado y metadatos
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Índices para búsqueda rápida
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'full_path']);
            $table->index(['company_id', 'code']);
            $table->index('capacity_used'); // Para alertas de capacidad

            // Full-text search en path y código (solo MySQL/MariaDB)
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->fullText(['full_path', 'code'], 'physical_locations_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_locations');
    }
};
