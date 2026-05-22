<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\User;
use Database\Seeders\AguasDeSucrePhysicalLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a basement archive template and shelf locations for aguas de sucre', function () {
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

    User::query()->create([
        'name' => 'Kristian Orozco',
        'email' => 'ing.korozco@gmail.com',
        'password' => Hash::make('Admin123'),
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    $this->seed(AguasDeSucrePhysicalLocationSeeder::class);

    $template = PhysicalLocationTemplate::query()->where('company_id', $company->id)->firstOrFail();

    expect($template->name)->toBe('Archivo Principal Sótano')
        ->and($template->getLevelNames())->toBe(['Nivel', 'Archivo', 'Estante', 'Entrepaño'])
        ->and(PhysicalLocation::query()->where('company_id', $company->id)->count())->toBe(120)
        ->and(PhysicalLocation::query()->where('company_id', $company->id)->where('full_path', 'Nivel Sótano / Archivo Principal / Estante 01 / Entrepaño 01')->exists())->toBeTrue()
        ->and(PhysicalLocation::query()->where('company_id', $company->id)->where('full_path', 'Nivel Sótano / Archivo Principal / Estante 20 / Entrepaño 06')->exists())->toBeTrue()
        ->and(PhysicalLocation::query()->where('company_id', $company->id)->where('code', 'C1/NIV-Sótano/ARC-Principal/EST-01/ENT-01')->exists())->toBeTrue();
});
