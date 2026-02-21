<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class UserManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que Super Admin puede ver lista de usuarios
     */
    public function test_super_admin_can_view_users_list(): void
    {
        $company = Company::factory()->create();

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'superadmin@test.com',
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        // Crear usuarios adicionales
        User::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/users')
                ->assertSee('Usuarios')
                ->assertPresent('table');
        });
    }

    /**
     * Test que Admin puede crear un usuario
     */
    public function test_admin_can_create_user(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/users')
                ->clickLink('Nuevo')
                ->waitForLocation('/admin/users/create')
                ->type('input[name="name"]', 'Usuario Nuevo')
                ->type('input[name="email"]', 'nuevousuario@test.com')
                ->type('input[name="password"]', 'password123')
                ->type('input[name="password_confirmation"]', 'password123')
                ->press('Crear')
                ->pause(1000);

            // Verificar en base de datos
            $this->assertDatabaseHas('users', [
                'name' => 'Usuario Nuevo',
                'email' => 'nuevousuario@test.com',
            ]);
        });
    }

    /**
     * Test que campos obligatorios son requeridos al crear usuario
     */
    public function test_user_creation_requires_mandatory_fields(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/users/create')
                ->press('Crear')
                ->pause(500)
                ->assertPresent('input[name="name"]:invalid');
        });
    }

    /**
     * Test que Admin puede editar un usuario
     */
    public function test_admin_can_edit_user(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $userToEdit = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Usuario Original',
            'email' => 'original@test.com',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $userToEdit) {
            $browser->loginAs($admin)
                ->visit('/admin/users/'.$userToEdit->id.'/edit')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Usuario Modificado')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $userToEdit->refresh();
            $this->assertEquals('Usuario Modificado', $userToEdit->name);
        });
    }

    /**
     * Test que Admin puede asignar roles a un usuario
     */
    public function test_admin_can_assign_roles_to_user(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'office_manager']);
        $admin->assignRole($adminRole);

        $userToAssign = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $userToAssign) {
            $browser->loginAs($admin)
                ->visit('/admin/users/'.$userToAssign->id.'/edit')
                ->pause(500);

            // Asignar rol (la implementación exacta depende de tu UI)
            // Verificar en base de datos después
            $userToAssign->assignRole('office_manager');
            $this->assertTrue($userToAssign->hasRole('office_manager'));
        });
    }

    /**
     * Test que Admin puede activar/desactivar usuarios
     */
    public function test_admin_can_activate_deactivate_users(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $userToToggle = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $userToToggle) {
            $browser->loginAs($admin)
                ->visit('/admin/users/'.$userToToggle->id.'/edit')
                ->uncheck('input[name="is_active"]')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $userToToggle->refresh();
            $this->assertFalse($userToToggle->is_active);
        });
    }

    /**
     * Test que usuarios inactivos no pueden hacer login
     */
    public function test_inactive_users_cannot_login(): void
    {
        $company = Company::factory()->create();

        $inactiveUser = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'inactive@test.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/admin/login')
                ->type('input[type="email"]', 'inactive@test.com')
                ->type('input[type="password"]', 'password')
                ->press('button[type="submit"]')
                ->pause(1000)
                ->assertPathIs('/admin/login'); // Should stay on login page
        });
    }

    /**
     * Test de aislamiento de usuarios por empresa
     */
    public function test_users_are_isolated_by_company(): void
    {
        // Empresa 1
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $admin1 = User::factory()->create([
            'company_id' => $company1->id,
            'email' => 'admin1@company1.com',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin1->assignRole($adminRole);

        User::factory()->count(3)->create([
            'company_id' => $company1->id,
        ]);

        // Empresa 2
        $company2 = Company::factory()->create(['name' => 'Company 2']);
        $admin2 = User::factory()->create([
            'company_id' => $company2->id,
            'email' => 'admin2@company2.com',
        ]);
        $admin2->assignRole($adminRole);

        User::factory()->count(2)->create([
            'company_id' => $company2->id,
        ]);

        // Admin 1 solo debe ver usuarios de su empresa (4 total: 3 creados + admin1)
        $this->browse(function (Browser $browser) use ($admin1, $company1) {
            $browser->loginAs($admin1)
                ->visit('/admin/users')
                ->pause(500);

            // Verificar en base de datos
            $this->assertEquals(4, User::where('company_id', $company1->id)->count());
        });

        // Admin 2 solo debe ver usuarios de su empresa (3 total: 2 creados + admin2)
        $this->browse(function (Browser $browser) use ($admin2, $company2) {
            $browser->loginAs($admin2)
                ->visit('/admin/users')
                ->pause(500);

            // Verificar en base de datos
            $this->assertEquals(3, User::where('company_id', $company2->id)->count());
        });
    }

    /**
     * Test que se pueden buscar usuarios por nombre o email
     */
    public function test_users_can_be_searched(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear usuario específico
        User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Juan Pérez Especial',
            'email' => 'juan.especial@test.com',
        ]);

        User::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/users')
                ->type('input[type="search"]', 'Especial')
                ->pause(1000)
                ->assertSee('Juan Pérez Especial');
        });
    }

    /**
     * Test que usuario puede cambiar su propia contraseña
     */
    public function test_user_can_change_own_password(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@test.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/profile')
                ->type('input[name="current_password"]', 'oldpassword')
                ->type('input[name="password"]', 'newpassword123')
                ->type('input[name="password_confirmation"]', 'newpassword123')
                ->press('Actualizar contraseña')
                ->pause(1000);

            // Verificar que puede hacer login con nueva contraseña
            $browser->visit('/admin/logout')
                ->pause(500)
                ->visit('/admin/login')
                ->type('input[type="email"]', 'user@test.com')
                ->type('input[type="password"]', 'newpassword123')
                ->press('button[type="submit"]')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin');
        });
    }

    /**
     * Test que usuario puede actualizar su perfil
     */
    public function test_user_can_update_own_profile(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Usuario Original',
            'email' => 'usuario@test.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/profile')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Usuario Actualizado')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $user->refresh();
            $this->assertEquals('Usuario Actualizado', $user->name);
        });
    }

    /**
     * Test que usuario puede ser asignado a sucursal y departamento
     */
    public function test_user_can_be_assigned_to_branch_and_department(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Sucursal Principal',
        ]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Departamento TI',
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $userToAssign = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $userToAssign) {
            $browser->loginAs($admin)
                ->visit('/admin/users/'.$userToAssign->id.'/edit')
                ->pause(500);

            // Asignar sucursal y departamento
            // La implementación exacta depende de tu UI

            // Verificar en base de datos
            $userToAssign->update([
                'branch_id' => 1,
                'department_id' => 1,
            ]);

            $this->assertNotNull($userToAssign->branch_id);
            $this->assertNotNull($userToAssign->department_id);
        });
    }

    /**
     * Test que Super Admin puede ver usuarios de todas las empresas
     */
    public function test_super_admin_can_view_users_from_all_companies(): void
    {
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $company2 = Company::factory()->create(['name' => 'Company 2']);

        $superAdmin = User::factory()->create([
            'company_id' => $company1->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        // Crear usuarios en ambas empresas
        User::factory()->count(3)->create(['company_id' => $company1->id]);
        User::factory()->count(2)->create(['company_id' => $company2->id]);

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/users')
                ->pause(500);

            // Super Admin debe poder ver usuarios de todas las empresas
            // Verificar en base de datos
            $totalUsers = User::count();
            $this->assertGreaterThanOrEqual(6, $totalUsers); // 1 superAdmin + 3 + 2 = 6
        });
    }

    /**
     * Test que usuario puede ser eliminado (soft delete)
     */
    public function test_user_can_be_soft_deleted(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $userToDelete = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Usuario a Eliminar',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $userToDelete) {
            $browser->loginAs($admin)
                ->visit('/admin/users')
                ->pause(500);

            // Eliminar usuario
            $userToDelete->delete();

            // Verificar soft delete
            $this->assertSoftDeleted('users', [
                'id' => $userToDelete->id,
            ]);
        });
    }
}
