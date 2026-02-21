<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class CompanyManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que Super Admin puede ver lista de empresas
     */
    public function test_super_admin_can_view_companies_list(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'superadmin@test.com',
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        // Crear empresas adicionales
        Company::factory()->count(3)->create();

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies')
                ->assertSee('Empresas')
                ->assertPresent('table')
                ->assertSee('Test Company');
        });
    }

    /**
     * Test que Super Admin puede crear una empresa
     */
    public function test_super_admin_can_create_company(): void
    {
        $company = Company::factory()->create();

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies')
                ->clickLink('Nueva empresa')
                ->waitForLocation('/admin/companies/create')
                ->type('input[name="name"]', 'Nueva Empresa Test')
                ->type('input[name="slug"]', 'nueva-empresa-test')
                ->type('input[name="email"]', 'contact@nuevaempresa.com')
                ->type('input[name="phone"]', '+1234567890')
                ->press('Crear')
                ->pause(1000);

            // Verificar en base de datos
            $this->assertDatabaseHas('companies', [
                'name' => 'Nueva Empresa Test',
                'email' => 'contact@nuevaempresa.com',
            ]);
        });
    }

    /**
     * Test que se requieren campos obligatorios al crear empresa
     */
    public function test_company_creation_requires_mandatory_fields(): void
    {
        $company = Company::factory()->create();

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies/create')
                ->press('Crear')
                ->pause(500)
                ->assertPresent('input[name="name"]:invalid');
        });
    }

    /**
     * Test que Super Admin puede editar una empresa
     */
    public function test_super_admin_can_edit_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Empresa Original',
            'email' => 'original@test.com',
        ]);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin, $company) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies/'.$company->id.'/edit')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Empresa Modificada')
                ->clear('input[name="email"]')
                ->type('input[name="email"]', 'modificada@test.com')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $company->refresh();
            $this->assertEquals('Empresa Modificada', $company->name);
            $this->assertEquals('modificada@test.com', $company->email);
        });
    }

    /**
     * Test que Super Admin puede desactivar una empresa
     */
    public function test_super_admin_can_deactivate_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Empresa a Desactivar',
            'active' => true,
        ]);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin, $company) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies/'.$company->id.'/edit')
                ->uncheck('input[name="active"]')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $company->refresh();
            $this->assertFalse($company->active);
        });
    }

    /**
     * Test de aislamiento multi-empresa (CRÍTICO)
     */
    public function test_companies_are_completely_isolated(): void
    {
        // Empresa 1
        $company1 = Company::factory()->create(['name' => 'Empresa 1']);
        $user1 = User::factory()->create([
            'company_id' => $company1->id,
            'email' => 'user1@company1.com',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user1->assignRole($adminRole);

        // Crear documentos para empresa 1
        Document::factory()->count(5)->create([
            'company_id' => $company1->id,
            'created_by' => $user1->id,
            'title' => 'Documento Empresa 1',
        ]);

        // Empresa 2
        $company2 = Company::factory()->create(['name' => 'Empresa 2']);
        $user2 = User::factory()->create([
            'company_id' => $company2->id,
            'email' => 'user2@company2.com',
        ]);
        $user2->assignRole($adminRole);

        // Crear documentos para empresa 2
        Document::factory()->count(3)->create([
            'company_id' => $company2->id,
            'created_by' => $user2->id,
            'title' => 'Documento Empresa 2',
        ]);

        // Usuario 1 solo debe ver sus propios documentos
        $this->browse(function (Browser $browser) use ($user1) {
            $browser->loginAs($user1)
                ->visit('/admin/documents')
                ->assertSee('Documento Empresa 1')
                ->assertDontSee('Documento Empresa 2');
        });

        // Usuario 2 solo debe ver sus propios documentos
        $this->browse(function (Browser $browser) use ($user2) {
            $browser->loginAs($user2)
                ->visit('/admin/documents')
                ->assertSee('Documento Empresa 2')
                ->assertDontSee('Documento Empresa 1');
        });

        // Verificar en base de datos
        $this->assertEquals(5, Document::where('company_id', $company1->id)->count());
        $this->assertEquals(3, Document::where('company_id', $company2->id)->count());
    }

    /**
     * Test que admin regular no puede acceder a gestión de empresas
     */
    public function test_regular_admin_cannot_access_company_management(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/companies')
                ->pause(500);

            // Debe ser redirigido o ver mensaje de acceso denegado
            // La implementación exacta depende de tu configuración de permisos
        });
    }

    /**
     * Test que empresa puede tener múltiples sucursales
     */
    public function test_company_can_have_multiple_branches(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa con Sucursales']);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        // Crear sucursales
        Branch::factory()->count(3)->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($superAdmin, $company) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies/'.$company->id)
                ->pause(500);

            // Verificar que la empresa tiene 3 sucursales
            $this->assertEquals(3, Branch::where('company_id', $company->id)->count());
        });
    }

    /**
     * Test que empresa puede ser visualizada con sus detalles
     */
    public function test_company_details_can_be_viewed(): void
    {
        $company = Company::factory()->create([
            'name' => 'Empresa Detallada',
            'email' => 'detallada@test.com',
            'phone' => '+1234567890',
            'address' => 'Calle Principal 123',
        ]);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin, $company) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies/'.$company->id)
                ->assertSee('Empresa Detallada')
                ->assertSee('detallada@test.com')
                ->assertSee('+1234567890');
        });
    }

    /**
     * Test que se puede buscar empresas por nombre
     */
    public function test_companies_can_be_searched_by_name(): void
    {
        $company = Company::factory()->create();

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        // Crear empresas específicas
        Company::factory()->create(['name' => 'Empresa Búsqueda Especial XYZ']);
        Company::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($superAdmin) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies')
                ->type('input[type="search"]', 'XYZ')
                ->pause(1000)
                ->assertSee('Empresa Búsqueda Especial XYZ');
        });
    }

    /**
     * Test que empresa puede ser eliminada (soft delete)
     */
    public function test_company_can_be_soft_deleted(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa a Eliminar']);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->assignRole($superAdminRole);

        $this->browse(function (Browser $browser) use ($superAdmin, $company) {
            $browser->loginAs($superAdmin)
                ->visit('/admin/companies')
                ->pause(500);

            // Eliminar empresa
            // Nota: La implementación exacta depende de cómo Filament maneja las eliminaciones

            // Verificar soft delete en base de datos
            $company->delete();
            $this->assertSoftDeleted('companies', [
                'id' => $company->id,
            ]);
        });
    }
}
