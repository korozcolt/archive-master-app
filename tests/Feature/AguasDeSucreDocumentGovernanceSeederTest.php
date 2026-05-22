<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use Database\Seeders\AguasDeSucreDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('synchronizes the audited aguas de sucre trd structure', function () {
    $company = Company::query()->create([
        'name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
        'legal_name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
        'active' => true,
    ]);

    $branch = $company->branches()->create([
        'code' => 'PRINCIPAL',
        'name' => ['es' => 'Sede Principal'],
        'city' => 'Sincelejo',
        'country' => 'Colombia',
        'active' => true,
    ]);

    $department = Department::query()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => 'Administración del sistema'],
        'code' => 'SYSADMIN',
        'description' => ['es' => null],
        'active' => true,
    ]);

    $company->users()->create([
        'name' => 'Kristian Orozco',
        'email' => 'ing.korozco@gmail.com',
        'password' => Hash::make('Admin123'),
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    Department::query()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => 'OFICINA ASESORA JURÍDICA'],
        'code' => 'OAJ160',
        'description' => ['es' => null],
        'active' => true,
    ]);

    $this->seed(AguasDeSucreDocumentGovernanceSeeder::class);

    expect(Department::query()->where('company_id', $company->id)->where('code', 'OAJ160')->value('active'))->toBeFalse()
        ->and(DocumentarySeries::query()->where('company_id', $company->id)->count())->toBe(53)
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->count())->toBe(113)
        ->and(DocumentaryType::query()->where('company_id', $company->id)->count())->toBeGreaterThan(113)
        ->and(RetentionSchedule::query()->where('company_id', $company->id)->count())->toBe(113);

    $seriesCountsByDepartment = DocumentarySeries::query()
        ->where('company_id', $company->id)
        ->selectRaw('department_id, count(*) as total')
        ->groupBy('department_id')
        ->pluck('total', 'department_id');

    $departmentCodes = Department::query()
        ->where('company_id', $company->id)
        ->pluck('code', 'id');

    expect($seriesCountsByDepartment[$departmentCodes->search('G100')])->toBe(7)
        ->and($seriesCountsByDepartment[$departmentCodes->search('SAF140')])->toBe(17)
        ->and($seriesCountsByDepartment[$departmentCodes->search('ST130')])->toBe(4)
        ->and($seriesCountsByDepartment[$departmentCodes->search('SAP150')])->toBe(5)
        ->and($seriesCountsByDepartment[$departmentCodes->search('CAI120')])->toBe(5)
        ->and($seriesCountsByDepartment[$departmentCodes->search('SG110')])->toBe(15);

    expect(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'CAI120-16-10')->where('name', 'Informe de Seguimiento de PQRS')->exists())->toBeTrue()
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'ST130-24-09')->where('name', 'Plan de Gestión Predial')->exists())->toBeTrue()
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'SAP150-25-01')->where('name', 'PQRS')->exists())->toBeTrue()
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'SAF140-28-10')->where('name', 'Registro de Notas y Ajustes Contables')->exists())->toBeTrue()
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'SG110-17-08')->where('name', 'Tablas de Retención Documental')->exists())->toBeTrue()
        ->and(DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'SG110-10-07')->where('name', 'Contrato de Prestación de Servicios Profesionales')->exists())->toBeTrue();

    expect(DocumentaryType::query()->where('company_id', $company->id)->where('name', 'Acta comité evaluador de documentos')->exists())->toBeTrue()
        ->and(DocumentaryType::query()->where('company_id', $company->id)->where('name', 'Informe de auditoría del ente de control externo')->exists())->toBeTrue()
        ->and(DocumentaryType::query()->where('company_id', $company->id)->where('name', 'Matriz de identificación de peligros y valoración de riesgos')->exists())->toBeTrue()
        ->and(DocumentaryType::query()->where('company_id', $company->id)->where('name', 'Certificados de satisfacción')->exists())->toBeTrue()
        ->and(DocumentaryType::query()->where('company_id', $company->id)->where('name', 'Auto de admisión')->exists())->toBeTrue();
});
