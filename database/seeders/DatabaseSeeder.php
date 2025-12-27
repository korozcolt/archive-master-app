<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear usuarios, empresas, sucursales, departamentos y roles/permisos
        $this->call(UserSeeder::class);

        // 2. Crear categorías para las empresas
        $this->call(CategorySeeder::class);

        // 3. Crear estados para documentos
        $this->call(StatusSeeder::class);

        // 4. Crear etiquetas para documentos
        $this->call(TagSeeder::class);

        // 5. Crear definiciones de flujo de trabajo
        $this->call(WorkflowDefinitionSeeder::class);

        // 6. Crear plantillas de documentos
        $this->call(DocumentTemplateSeeder::class);
    }
}
