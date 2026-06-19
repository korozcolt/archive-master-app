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
                'description' => 'Estructura de AGUAS DE SUCRE: sótano, archivo principal, estante, entrepaño y caja.',
                'is_active' => true,
                'levels' => [
                    ['order' => 1, 'name' => 'Nivel', 'code' => 'NIV', 'required' => true, 'icon' => 'heroicon-o-building-office-2'],
                    ['order' => 2, 'name' => 'Archivo', 'code' => 'ARC', 'required' => true, 'icon' => 'heroicon-o-archive-box'],
                    ['order' => 3, 'name' => 'Estante', 'code' => 'EST', 'required' => true, 'icon' => 'heroicon-o-bars-3-bottom-left'],
                    ['order' => 4, 'name' => 'Entrepaño', 'code' => 'ENT', 'required' => true, 'icon' => 'heroicon-o-rectangle-stack'],
                    ['order' => 5, 'name' => 'Caja', 'code' => 'CJ', 'required' => true, 'icon' => 'heroicon-o-cube'],
                ],
            ],
        );

        \Illuminate\Support\Facades\DB::transaction(function () use ($company, $template, $creator) {
            for ($shelf = 1; $shelf <= 40; $shelf++) {
                for ($bay = 1; $bay <= 6; $bay++) {
                    for ($box = 1; $box <= 8; $box++) {
                        $structuredData = [
                            'nivel' => 'Sótano',
                            'archivo' => 'Principal',
                            'estante' => str_pad((string) $shelf, 2, '0', STR_PAD_LEFT),
                            'entrepaño' => str_pad((string) $bay, 2, '0', STR_PAD_LEFT),
                            'caja' => str_pad((string) $box, 3, '0', STR_PAD_LEFT),
                        ];

                        $location = new PhysicalLocation([
                            'company_id' => $company->id,
                            'template_id' => $template->id,
                            'structured_data' => $structuredData,
                        ]);
                        $fullPath = $location->generateFullPath();

                        $existingLocation = PhysicalLocation::query()
                            ->where('company_id', $company->id)
                            ->where('full_path', $fullPath)
                            ->first();

                        PhysicalLocation::query()->updateOrCreate(
                            [
                                'company_id' => $company->id,
                                'full_path' => $fullPath,
                            ],
                            array_filter([
                                'template_id' => $template->id,
                                'structured_data' => $structuredData,
                                'code' => $location->generateCode(),
                                'capacity_total' => $existingLocation?->capacity_total ?? 25,
                                'capacity_used' => $existingLocation?->capacity_used ?? 0,
                                'is_active' => true,
                                'notes' => sprintf(
                                    'Caja AGUAS DE SUCRE - Estante %s / Entrepaño %s / Caja %s.',
                                    $structuredData['estante'],
                                    $structuredData['entrepaño'],
                                    $structuredData['caja'],
                                ),
                                'created_by' => $existingLocation?->created_by ?? $creator?->id,
                            ], fn (mixed $value): bool => $value !== null),
                        );
                    }
                }
            }
        });

        $this->command?->info('Plantilla y 1920 cajas físicas creadas o actualizadas para AGUAS DE SUCRE.');
    }
}
