<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhysicalLocationBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->get();

        if ($companies->isEmpty()) {
            $this->command?->warn('No hay empresas para sembrar ubicaciones físicas. Ejecute UserSeeder primero.');

            return;
        }

        foreach ($companies as $company) {
            $this->seedCompanyPhysicalInfrastructure($company);
        }
    }

    private function seedCompanyPhysicalInfrastructure(Company $company): void
    {
        $creator = User::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        $template = PhysicalLocationTemplate::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Estructura Archivo Central',
            ],
            [
                'description' => 'Estructura base para gestión de archivo físico (edificio, piso, sala, armario, estante, caja).',
                'is_active' => true,
                'levels' => [
                    ['order' => 1, 'name' => 'Edificio', 'code' => 'ED', 'required' => true, 'icon' => 'heroicon-o-building-office-2'],
                    ['order' => 2, 'name' => 'Piso', 'code' => 'P', 'required' => true, 'icon' => 'heroicon-o-squares-2x2'],
                    ['order' => 3, 'name' => 'Sala', 'code' => 'SAL', 'required' => true, 'icon' => 'heroicon-o-archive-box'],
                    ['order' => 4, 'name' => 'Armario', 'code' => 'ARM', 'required' => true, 'icon' => 'heroicon-o-rectangle-stack'],
                    ['order' => 5, 'name' => 'Estante', 'code' => 'EST', 'required' => true, 'icon' => 'heroicon-o-bars-3-bottom-left'],
                    ['order' => 6, 'name' => 'Caja', 'code' => 'CJ', 'required' => true, 'icon' => 'heroicon-o-cube'],
                ],
            ]
        );

        $locations = [
            [
                'structured_data' => ['edificio' => 'A', 'piso' => '1', 'sala' => 'Archivo Central', 'armario' => '01', 'estante' => 'A', 'caja' => '001'],
                'capacity_total' => 300,
                'notes' => 'Ubicación principal para radicados recientes.',
            ],
            [
                'structured_data' => ['edificio' => 'A', 'piso' => '1', 'sala' => 'Archivo Central', 'armario' => '01', 'estante' => 'A', 'caja' => '002'],
                'capacity_total' => 300,
                'notes' => 'Continuación de archivo principal.',
            ],
            [
                'structured_data' => ['edificio' => 'A', 'piso' => '1', 'sala' => 'Archivo Central', 'armario' => '01', 'estante' => 'B', 'caja' => '001'],
                'capacity_total' => 250,
                'notes' => 'Documentos administrativos.',
            ],
            [
                'structured_data' => ['edificio' => 'A', 'piso' => '1', 'sala' => 'Archivo Central', 'armario' => '02', 'estante' => 'A', 'caja' => '001'],
                'capacity_total' => 250,
                'notes' => 'Documentos contables.',
            ],
            [
                'structured_data' => ['edificio' => 'A', 'piso' => '2', 'sala' => 'Archivo Histórico', 'armario' => '03', 'estante' => 'C', 'caja' => '010'],
                'capacity_total' => 500,
                'notes' => 'Archivo histórico de conservación.',
            ],
            [
                'structured_data' => ['edificio' => 'B', 'piso' => '1', 'sala' => 'Archivo Norte', 'armario' => '01', 'estante' => 'A', 'caja' => '001'],
                'capacity_total' => 200,
                'notes' => 'Ubicación para apoyo sucursal norte.',
            ],
        ];

        foreach ($locations as $locationData) {
            $structuredData = $locationData['structured_data'];
            $code = $this->buildCode($company->id, $structuredData);
            $fullPath = $this->buildFullPath($structuredData);
            $location = PhysicalLocation::query()
                ->where('company_id', $company->id)
                ->where('full_path', $fullPath)
                ->first() ?? new PhysicalLocation;

            $location->fill([
                'company_id' => $company->id,
                'template_id' => $template->id,
                'code' => $code,
                'full_path' => $fullPath,
                'structured_data' => $structuredData,
                'capacity_total' => $locationData['capacity_total'],
                'capacity_used' => $location->exists ? (int) $location->capacity_used : 0,
                'is_active' => true,
                'notes' => $locationData['notes'],
                'created_by' => $location->exists ? $location->created_by : $creator?->id,
            ]);

            $location->save();
        }

        $this->command?->info("Infraestructura física base cargada para {$company->name}: plantilla + ".count($locations).' ubicaciones.');
    }

    /**
     * @param  array<string, string>  $structuredData
     */
    private function buildCode(int $companyId, array $structuredData): string
    {
        return sprintf(
            'C%s/ED-%s/P-%s/SAL-%s/ARM-%s/EST-%s/CJ-%s',
            $companyId,
            strtoupper((string) ($structuredData['edificio'] ?? 'NA')),
            strtoupper((string) ($structuredData['piso'] ?? 'NA')),
            strtoupper((string) str_replace(' ', '', (string) ($structuredData['sala'] ?? 'NA'))),
            strtoupper((string) ($structuredData['armario'] ?? 'NA')),
            strtoupper((string) ($structuredData['estante'] ?? 'NA')),
            strtoupper((string) ($structuredData['caja'] ?? 'NA')),
        );
    }

    /**
     * @param  array<string, string>  $structuredData
     */
    private function buildFullPath(array $structuredData): string
    {
        return sprintf(
            'Edificio %s / Piso %s / %s / Armario %s / Estante %s / Caja %s',
            (string) ($structuredData['edificio'] ?? 'N/A'),
            (string) ($structuredData['piso'] ?? 'N/A'),
            (string) ($structuredData['sala'] ?? 'Ubicación'),
            (string) ($structuredData['armario'] ?? 'N/A'),
            (string) ($structuredData['estante'] ?? 'N/A'),
            (string) ($structuredData['caja'] ?? 'N/A'),
        );
    }
}
