<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StatusSeeder extends Seeder
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

        // Para cada empresa, crear los estados predeterminados
        foreach ($companies as $company) {
            $this->command->info("Creando estados para la empresa: {$company->name}");

            $this->createDefaultStatuses($company);

            // Crear estados adicionales para todas las empresas
            $this->createAdditionalStatuses($company);
        }
    }

    /**
     * Crear estados predeterminados basados en el enum DocumentStatus
     */
    private function createDefaultStatuses(Company $company): void
    {
        foreach (DocumentStatus::cases() as $status) {
            Status::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => $status->value,
                ],
                [
                    'name' => $status->getLabel(),
                    'color' => $status->getColor(),
                    'icon' => $status->getIcon(),
                    'is_initial' => in_array($status, [DocumentStatus::Received, DocumentStatus::Draft]),
                    'is_final' => in_array($status, [DocumentStatus::Archived, DocumentStatus::Rejected, DocumentStatus::Approved]),
                    'active' => true,
                    'order' => $this->getStatusOrder($status),
                ]
            );
        }
    }

    /**
     * Crear estados adicionales para pruebas
     */
    private function createAdditionalStatuses(Company $company): void
    {
        $additionalStatuses = [
            [
                'name' => 'En Correcciones',
                'slug' => 'in-corrections',
                'description' => 'Documento que está siendo corregido',
                'color' => 'warning',
                'icon' => 'heroicon-o-pencil-square',
                'is_initial' => false,
                'is_final' => false,
                'order' => 9,
            ],
            [
                'name' => 'Esperando Firma',
                'slug' => 'awaiting-signature',
                'description' => 'Documento pendiente de firma',
                'color' => 'info',
                'icon' => 'heroicon-o-pencil',
                'is_initial' => false,
                'is_final' => false,
                'order' => 10,
            ],
            [
                'name' => 'Cancelado',
                'slug' => 'cancelled',
                'description' => 'Documento cancelado',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-mark',
                'is_initial' => false,
                'is_final' => true,
                'order' => 11,
            ],
            [
                'name' => 'Reemplazado',
                'slug' => 'replaced',
                'description' => 'Documento reemplazado por una nueva versión',
                'color' => 'gray',
                'icon' => 'heroicon-o-arrow-path',
                'is_initial' => false,
                'is_final' => true,
                'order' => 12,
            ],
        ];

        foreach ($additionalStatuses as $statusData) {
            Status::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => $statusData['slug'],
                ],
                [
                    'name' => $statusData['name'],
                    'description' => $statusData['description'],
                    'color' => $statusData['color'],
                    'icon' => $statusData['icon'],
                    'is_initial' => $statusData['is_initial'],
                    'is_final' => $statusData['is_final'],
                    'active' => true,
                    'order' => $statusData['order'],
                ]
            );
        }

        // Crear estado inactivo para pruebas
        Status::firstOrCreate(
            [
                'company_id' => $company->id,
                'slug' => 'inactive-status',
            ],
            [
                'name' => 'Estado Inactivo',
                'description' => 'Estado inactivo para pruebas',
                'color' => 'gray',
                'icon' => 'heroicon-o-x-circle',
                'is_initial' => false,
                'is_final' => false,
                'active' => false,
                'order' => 99,
            ]
        );
    }

    /**
     * Obtener el orden para los estados predeterminados
     */
    private function getStatusOrder(DocumentStatus $status): int
    {
        return match ($status) {
            DocumentStatus::Draft => 1,
            DocumentStatus::Received => 2,
            DocumentStatus::InProcess => 3,
            DocumentStatus::UnderReview => 4,
            DocumentStatus::Approved => 5,
            DocumentStatus::Rejected => 6,
            DocumentStatus::Archived => 7,
            DocumentStatus::Expired => 8,
        };
    }
}
