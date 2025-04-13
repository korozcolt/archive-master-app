<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener empresas existentes (creadas por UserSeeder)
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->info('No hay empresas disponibles. Ejecute UserSeeder primero.');
            return;
        }

        // Tags comunes para todas las empresas
        $commonTags = [
            [
                'name' => 'Urgente',
                'description' => 'Documentos que requieren atención inmediata',
                'color' => 'danger',
                'icon' => 'heroicon-o-fire',
            ],
            [
                'name' => 'Importante',
                'description' => 'Documentos prioritarios',
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            [
                'name' => 'Confidencial',
                'description' => 'Documentos con información sensible',
                'color' => 'purple',
                'icon' => 'heroicon-o-lock-closed',
            ],
            [
                'name' => 'Para Revisar',
                'description' => 'Documentos pendientes de revisión',
                'color' => 'info',
                'icon' => 'heroicon-o-magnifying-glass',
            ],
            [
                'name' => 'Aprobado',
                'description' => 'Documentos aprobados',
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'name' => 'Rechazado',
                'description' => 'Documentos rechazados',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
        ];

        // Para cada empresa, crear las etiquetas
        foreach ($companies as $company) {
            $this->command->info("Creando etiquetas para la empresa: {$company->name}");

            // Crear etiquetas comunes
            foreach ($commonTags as $tagData) {
                Tag::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($tagData['name']),
                    ],
                    [
                        'name' => $tagData['name'],
                        'description' => $tagData['description'],
                        'color' => $tagData['color'],
                        'icon' => $tagData['icon'],
                        'active' => true,
                    ]
                );
            }

            // Crear etiquetas específicas
            $specificTags = [
                [
                    'name' => 'Digital',
                    'description' => 'Documento digitalizado',
                    'color' => 'info',
                    'icon' => 'heroicon-o-computer-desktop',
                ],
                [
                    'name' => 'Físico',
                    'description' => 'Documento en formato físico',
                    'color' => 'gray',
                    'icon' => 'heroicon-o-document',
                ],
                [
                    'name' => 'Original',
                    'description' => 'Documento original',
                    'color' => 'success',
                    'icon' => 'heroicon-o-check-badge',
                ],
                [
                    'name' => 'Copia',
                    'description' => 'Copia de documento',
                    'color' => 'gray',
                    'icon' => 'heroicon-o-document-duplicate',
                ],
                [
                    'name' => 'Archivo Central',
                    'description' => 'Documento en archivo central',
                    'color' => 'blue',
                    'icon' => 'heroicon-o-archive-box',
                ],
                [
                    'name' => 'Archivo Histórico',
                    'description' => 'Documento en archivo histórico',
                    'color' => 'yellow',
                    'icon' => 'heroicon-o-archive-box-arrow-down',
                ],
            ];

            foreach ($specificTags as $tagData) {
                Tag::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($tagData['name']),
                    ],
                    [
                        'name' => $tagData['name'],
                        'description' => $tagData['description'],
                        'color' => $tagData['color'],
                        'icon' => $tagData['icon'],
                        'active' => true,
                    ]
                );
            }

            // Crear una etiqueta inactiva para pruebas
            Tag::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => 'etiqueta-inactiva',
                ],
                [
                    'name' => 'Etiqueta Inactiva',
                    'description' => 'Etiqueta inactiva para pruebas',
                    'color' => 'gray',
                    'icon' => 'heroicon-o-x-circle',
                    'active' => false,
                ]
            );
        }
    }
}
