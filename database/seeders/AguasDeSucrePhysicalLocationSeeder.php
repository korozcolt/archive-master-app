<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class AguasDeSucrePhysicalLocationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $creator = User::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        $template = PhysicalLocationTemplate::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Archivo Principal Sótano',
            ],
            [
                'description' => 'Estructura de prueba para AGUAS DE SUCRE: sótano, archivo principal, estante y entrepaño.',
                'is_active' => true,
                'levels' => [
                    ['order' => 1, 'name' => 'Nivel', 'code' => 'NIV', 'required' => true, 'icon' => 'heroicon-o-building-office-2'],
                    ['order' => 2, 'name' => 'Archivo', 'code' => 'ARC', 'required' => true, 'icon' => 'heroicon-o-archive-box'],
                    ['order' => 3, 'name' => 'Estante', 'code' => 'EST', 'required' => true, 'icon' => 'heroicon-o-bars-3-bottom-left'],
                    ['order' => 4, 'name' => 'Entrepaño', 'code' => 'ENT', 'required' => true, 'icon' => 'heroicon-o-rectangle-stack'],
                ],
            ],
        );

        for ($shelf = 1; $shelf <= 20; $shelf++) {
            for ($bay = 1; $bay <= 6; $bay++) {
                $structuredData = [
                    'nivel' => 'Sótano',
                    'archivo' => 'Principal',
                    'estante' => str_pad((string) $shelf, 2, '0', STR_PAD_LEFT),
                    'entrepaño' => str_pad((string) $bay, 2, '0', STR_PAD_LEFT),
                ];

                $location = new PhysicalLocation([
                    'company_id' => $company->id,
                    'template_id' => $template->id,
                    'structured_data' => $structuredData,
                ]);

                PhysicalLocation::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'full_path' => $location->generateFullPath(),
                    ],
                    [
                        'template_id' => $template->id,
                        'structured_data' => $structuredData,
                        'code' => $location->generateCode(),
                        'capacity_total' => 100,
                        'capacity_used' => 0,
                        'is_active' => true,
                        'notes' => 'Ubicación de prueba AGUAS DE SUCRE - Estante '.str_pad((string) $shelf, 2, '0', STR_PAD_LEFT).' / Entrepaño '.str_pad((string) $bay, 2, '0', STR_PAD_LEFT).'.',
                        'created_by' => $creator?->id,
                    ],
                );
            }
        }

        $this->command?->info('Plantilla y 120 ubicaciones físicas de prueba creadas para AGUAS DE SUCRE.');
    }
}
