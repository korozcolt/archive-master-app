<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

class ClientDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $this->createPermissions();

        $company = Company::query()->firstOrCreate(
            ['name' => 'AGUAS DE SUCRE S.A. E.S.P.'],
            [
                'legal_name' => 'AGUAS DE SUCRE S.A. E.S.P.',
                'active' => true,
            ]
        );

        $branch = Branch::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'PRINCIPAL',
            ],
            [
                'name' => 'Sede Principal',
                'city' => 'Sincelejo',
                'country' => 'Colombia',
                'active' => true,
            ]
        );

        $department = Department::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'SYSADMIN',
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Administración del sistema',
                'active' => true,
            ]
        );

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'ing.korozco@gmail.com'],
            [
                'name' => 'Kristian Orozco',
                'password' => Hash::make('Admin123'),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'position' => 'Administrador del sistema',
                'phone' => '3016859339',
                'language' => 'es',
                'timezone' => 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->forceFill([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'name' => 'Kristian Orozco',
            'password' => Hash::make('Admin123'),
            'phone' => '3016859339',
            'position' => 'Administrador del sistema',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $superAdmin->syncRoles([Role::SuperAdmin->value]);

        User::query()
            ->whereKeyNot($superAdmin->getKey())
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            });

        $this->command?->info('Seeder cliente: instancia base creada solo con super_admin.');
    }

    private function createPermissions(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::query()->firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        foreach (Role::getAllPermissions() as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach (Role::cases() as $role) {
            $roleModel = SpatieRole::query()->where('name', $role->value)->where('guard_name', 'web')->firstOrFail();
            $permissions = $role->getPermissions();

            if (in_array('*', $permissions, true)) {
                $roleModel->syncPermissions(Permission::all());

                continue;
            }

            $roleModel->syncPermissions($permissions);
        }
    }
}
