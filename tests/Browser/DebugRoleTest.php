<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class DebugRoleTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test debug de roles
     */
    public function test_debug_user_roles(): void
    {
        // Crear datos
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        // Limpiar cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $user->refresh();

        // Debug: Verificar roles
        dump('User ID: ' . $user->id);
        dump('User Roles: ' . $user->roles->pluck('name')->implode(', '));
        dump('Has admin role: ' . ($user->hasRole('admin') ? 'YES' : 'NO'));
        dump('Has regular_user role: ' . ($user->hasRole('regular_user') ? 'YES' : 'NO'));
        dump('Has any admin roles: ' . ($user->hasAnyRole(['admin', 'super_admin', 'branch_admin', 'office_manager']) ? 'YES' : 'NO'));

        $this->assertTrue($user->hasRole('regular_user'));
        $this->assertFalse($user->hasRole('admin'));
    }
}
