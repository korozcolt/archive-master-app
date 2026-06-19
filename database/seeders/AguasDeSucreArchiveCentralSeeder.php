<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class AguasDeSucreArchiveCentralSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->get()
            ->first(function (Company $company): bool {
                $name = $company->getTranslation('name', 'es', false) ?: data_get($company->name, 'es') ?: $company->name;

                return (string) $name === 'AGUAS DE SUCRE S.A. E.S.P.';
            });

        if (! $company) {
            $company = Company::query()->firstOrFail();
        }

        $branchId = (int) User::query()
            ->where('company_id', $company->id)
            ->value('branch_id');

        $department = Department::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'AC170',
            ],
            [
                'branch_id' => $branchId ?: null,
                'name' => [
                    'es' => 'Archivo Central',
                    'en' => 'Central Archive',
                ],
                'description' => [
                    'es' => 'Dependencia responsable de la custodia, organización y consulta del archivo central e histórico.',
                    'en' => 'Department responsible for custody, organization and consultation of central and historical archives.',
                ],
                'active' => true,
            ]
        );

        SpatieRole::firstOrCreate(['name' => Role::ArchiveManager->value]);
        SpatieRole::firstOrCreate(['name' => Role::ArchiveOperator->value]);

        $user = User::query()->updateOrCreate(
            ['email' => 'archivo.central.aguasdesucre@test.local'],
            [
                'name' => 'Archivo Central ADS',
                'password' => Hash::make('Archivo123*'),
                'company_id' => $company->id,
                'branch_id' => $department->branch_id,
                'department_id' => $department->id,
                'position' => 'Archivo Central',
                'language' => 'es',
                'timezone' => 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([Role::ArchiveManager->value]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin.archivo@aguas.local'],
            [
                'name' => 'Administrador de Archivo',
                'password' => Hash::make('ArchiveMaster2026!'),
                'company_id' => $company->id,
                'branch_id' => $department->branch_id,
                'department_id' => $department->id,
                'position' => 'Administrador de Archivo',
                'language' => 'es',
                'timezone' => 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([Role::ArchiveManager->value]);

        $operator = User::query()->updateOrCreate(
            ['email' => 'operador.archivo@aguas.local'],
            [
                'name' => 'Operador de Archivo',
                'password' => Hash::make('ArchiveMaster2026!'),
                'company_id' => $company->id,
                'branch_id' => $department->branch_id,
                'department_id' => $department->id,
                'position' => 'Operador de Archivo',
                'language' => 'es',
                'timezone' => 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $operator->syncRoles([Role::ArchiveOperator->value]);

        $this->command?->info('Archivo Central de AGUAS DE SUCRE configurado.');
    }
}
