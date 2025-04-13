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

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos basados en los enums de roles
        $this->createPermissions();

        // Crear empresa predeterminada
        $company = $this->createDefaultCompany();

        // Crear algunas sucursales
        $branches = $this->createBranches($company);

        // Crear algunos departamentos
        $departments = $this->createDepartments($company, $branches);

        // Crear el superadministrador
        $this->createSuperAdmin($company, $branches[0], $departments[0]);

        // Crear usuario para cada rol
        $this->createUsersForRoles($company, $branches, $departments);
    }

    /**
     * Crear los permisos basados en los enums de roles
     */
    private function createPermissions(): void
    {
        // Crear roles de Spatie
        foreach (Role::cases() as $role) {
            SpatieRole::firstOrCreate(['name' => $role->value]);
        }

        // Crear todos los permisos únicos de los roles
        $allPermissions = Role::getAllPermissions();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Asignar permisos a cada rol
        foreach (Role::cases() as $role) {
            $roleModel = SpatieRole::findByName($role->value);
            $permissions = $role->getPermissions();

            // Si el rol tiene permiso a todo, le damos todos los permisos
            if (in_array('*', $permissions)) {
                $roleModel->syncPermissions(Permission::all());
            } else {
                $roleModel->syncPermissions($permissions);
            }
        }
    }

    /**
     * Crear la empresa predeterminada
     */
    private function createDefaultCompany(): Company
    {
        return Company::firstOrCreate(
            ['name' => 'ArchiveMaster Corp'],
            [
                'legal_name' => 'ArchiveMaster Corporation S.A.S.',
                'tax_id' => '900123456-7',
                'address' => 'Calle Principal 123',
                'phone' => '+57 601 1234567',
                'email' => 'info@archivemaster.com',
                'website' => 'https://www.archivemaster.com',
                'primary_color' => '#41a6b3',
                'secondary_color' => '#f59e0b',
                'active' => true,
            ]
        );
    }

    /**
     * Crear sucursales para la empresa
     */
    private function createBranches(Company $company): array
    {
        $branchesData = [
            [
                'name' => 'Sede Principal',
                'code' => 'HQ',
                'city' => 'Bogotá',
                'country' => 'Colombia',
            ],
            [
                'name' => 'Sucursal Norte',
                'code' => 'NORTH',
                'city' => 'Medellín',
                'country' => 'Colombia',
            ],
            [
                'name' => 'Sucursal Sur',
                'code' => 'SOUTH',
                'city' => 'Cali',
                'country' => 'Colombia',
            ],
        ];

        $branches = [];

        foreach ($branchesData as $branchData) {
            $branches[] = Branch::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $branchData['code'],
                ],
                [
                    'name' => $branchData['name'],
                    'city' => $branchData['city'],
                    'country' => $branchData['country'],
                    'active' => true,
                ]
            );
        }

        return $branches;
    }

    /**
     * Crear departamentos para la empresa
     */
    private function createDepartments(Company $company, array $branches): array
    {
        $departmentsData = [
            [
                'name' => 'Administración',
                'code' => 'ADMIN',
                'branch_id' => $branches[0]->id,
                'parent_id' => null,
            ],
            [
                'name' => 'Recursos Humanos',
                'code' => 'RRHH',
                'branch_id' => $branches[0]->id,
                'parent_id' => null,
            ],
            [
                'name' => 'Contabilidad',
                'code' => 'CONT',
                'branch_id' => $branches[0]->id,
                'parent_id' => null,
            ],
            [
                'name' => 'Archivo Central',
                'code' => 'ARCH',
                'branch_id' => $branches[0]->id,
                'parent_id' => null,
            ],
            [
                'name' => 'Ventas Norte',
                'code' => 'SALES-N',
                'branch_id' => $branches[1]->id,
                'parent_id' => null,
            ],
            [
                'name' => 'Ventas Sur',
                'code' => 'SALES-S',
                'branch_id' => $branches[2]->id,
                'parent_id' => null,
            ],
        ];

        $departments = [];

        foreach ($departmentsData as $departmentData) {
            $departments[] = Department::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $departmentData['code'],
                ],
                [
                    'name' => $departmentData['name'],
                    'branch_id' => $departmentData['branch_id'],
                    'parent_id' => $departmentData['parent_id'],
                    'active' => true,
                ]
            );
        }

        return $departments;
    }

    /**
     * Crear el superadministrador
     */
    private function createSuperAdmin(Company $company, Branch $branch, Department $department): void
    {
        $user = User::firstOrCreate(
            ['email' => 'ing.korozco@gmail.com'],
            [
                'name' => 'Kristian Orozco',
                'password' => Hash::make('Q@10op29+'),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'position' => 'Super Administrador',
                'phone' => '+57 300 1234567',
                'language' => 'es',
                'timezone' => 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Asignar rol de superadmin
        $user->assignRole(Role::SuperAdmin->value);
    }

    /**
     * Crear usuarios para cada rol
     */
    private function createUsersForRoles(Company $company, array $branches, array $departments): void
    {
        $users = [
            [
                'name' => 'Admin Usuario',
                'email' => 'admin@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::Admin->value,
                'position' => 'Administrador',
                'branch_id' => $branches[0]->id,
                'department_id' => $departments[0]->id,
            ],
            [
                'name' => 'Gerente Sucursal',
                'email' => 'branch@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::BranchAdmin->value,
                'position' => 'Gerente de Sucursal',
                'branch_id' => $branches[1]->id,
                'department_id' => $departments[4]->id,
            ],
            [
                'name' => 'Encargado Oficina',
                'email' => 'office@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::OfficeManager->value,
                'position' => 'Encargado de Oficina',
                'branch_id' => $branches[0]->id,
                'department_id' => $departments[1]->id,
            ],
            [
                'name' => 'Archivista Principal',
                'email' => 'archive@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::ArchiveManager->value,
                'position' => 'Encargado de Archivo',
                'branch_id' => $branches[0]->id,
                'department_id' => $departments[3]->id,
            ],
            [
                'name' => 'Recepcionista',
                'email' => 'reception@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::Receptionist->value,
                'position' => 'Recepcionista',
                'branch_id' => $branches[0]->id,
                'department_id' => $departments[0]->id,
            ],
            [
                'name' => 'Usuario Regular',
                'email' => 'user@archivemaster.com',
                'password' => Hash::make('password'),
                'role' => Role::RegularUser->value,
                'position' => 'Contador',
                'branch_id' => $branches[0]->id,
                'department_id' => $departments[2]->id,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'company_id' => $company->id,
                    'branch_id' => $userData['branch_id'],
                    'department_id' => $userData['department_id'],
                    'position' => $userData['position'],
                    'language' => 'es',
                    'timezone' => 'America/Bogota',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            // Asignar rol
            $user->assignRole($userData['role']);
        }
    }
}
