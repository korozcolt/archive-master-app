<?php

namespace Database\Seeders;

use App\Enums\DocumentAccessLevel;
use App\Enums\FinalDisposition;
use App\Models\BusinessCalendar;
use App\Models\Company;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class ColombiaDocumentGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(function (Company $company): void {
            $this->seedBusinessCalendar($company);
            $this->seedSlaPolicies($company);
            $this->seedArchiveCatalog($company);
            $this->seedCompanySettings($company);
        });
    }

    private function seedBusinessCalendar(Company $company): void
    {
        BusinessCalendar::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Calendario hábil Colombia',
            ],
            [
                'country_code' => 'CO',
                'timezone' => 'America/Bogota',
                'weekend_days' => [0, 6],
                'is_default' => true,
                'metadata' => [
                    'source' => 'default-colombia',
                ],
            ]
        );
    }

    private function seedSlaPolicies(Company $company): void
    {
        $calendar = $company->businessCalendars()->where('is_default', true)->first();

        foreach (config('documents.governance.defaults.pqrs_policies', []) as $policy) {
            SlaPolicy::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $policy['code'],
                ],
                [
                    'business_calendar_id' => $calendar?->id,
                    'name' => $policy['name'],
                    'legal_basis' => $policy['legal_basis'],
                    'response_term_days' => $policy['response_term_days'],
                    'warning_days' => $policy['warning_days'],
                    'escalation_days' => config('documents.governance.defaults.escalation_days', 1),
                    'remission_deadline_days' => 5,
                    'requires_subsanation' => true,
                    'allows_extension' => true,
                    'is_active' => true,
                    'metadata' => [
                        'jurisdiction' => 'CO',
                    ],
                ]
            );
        }
    }

    private function seedArchiveCatalog(Company $company): void
    {
        $series = DocumentarySeries::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'PQRS',
            ],
            [
                'name' => 'Peticiones, quejas, reclamos y solicitudes',
                'description' => 'Serie base para trazabilidad de atención y archivo de PQRS.',
                'is_active' => true,
            ]
        );

        $subseries = DocumentarySubseries::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'documentary_series_id' => $series->id,
                'code' => 'TRAMITE',
            ],
            [
                'name' => 'Trámite y respuesta',
                'description' => 'Subserie que agrupa el expediente completo del trámite ciudadano.',
                'is_active' => true,
            ]
        );

        $documentaryType = DocumentaryType::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'documentary_subseries_id' => $subseries->id,
                'code' => 'EXP',
            ],
            [
                'name' => 'Expediente de PQRS',
                'description' => 'Documento archivístico consolidado con radicado, gestión y respuesta.',
                'access_level_default' => DocumentAccessLevel::Reservado,
                'is_active' => true,
            ]
        );

        RetentionSchedule::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'documentary_type_id' => $documentaryType->id,
            ],
            [
                'documentary_subseries_id' => $subseries->id,
                'archive_phase' => 'gestion',
                'management_years' => 2,
                'central_years' => 8,
                'historical_action' => 'Conservación histórica por trazabilidad institucional.',
                'final_disposition' => FinalDisposition::ConservacionTotal,
                'legal_basis' => 'TRD base ArchiveMaster para expedientes PQRS.',
                'is_active' => true,
            ]
        );
    }

    private function seedCompanySettings(Company $company): void
    {
        $settings = $company->settings ?? [];
        $company->forceFill([
            'settings' => array_replace_recursive($settings, [
                'document_governance' => [
                    'jurisdiction' => 'CO',
                    'timezone' => 'America/Bogota',
                    'warning_days' => config('documents.governance.defaults.warning_days', [3, 1]),
                    'escalation_days' => config('documents.governance.defaults.escalation_days', 1),
                    'allow_extension' => true,
                    'requires_subsanation' => true,
                    'archive_requires_trd' => true,
                    'archive_requires_access_level' => true,
                ],
            ]),
        ])->save();
    }
}
