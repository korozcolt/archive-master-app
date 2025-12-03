<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Document;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class BranchDepartmentTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que admin puede ver lista de sucursales
     */
    public function test_admin_can_view_branches_list(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        Branch::factory()->count(5)->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/branches')
                    ->assertSee('Sucursales')
                    ->assertPresent('table');
        });
    }

    /**
     * Test que admin puede crear una sucursal
     */
    public function test_admin_can_create_branch(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/branches/create')
                    ->type('input[name="name"]', 'Sucursal Principal')
                    ->type('input[name="address"]', 'Av. Principal 123')
                    ->type('input[name="phone"]', '+1234567890')
                    ->press('Crear')
                    ->pause(1000);

            $this->assertDatabaseHas('branches', [
                'name' => 'Sucursal Principal',
                'address' => 'Av. Principal 123',
            ]);
        });
    }

    /**
     * Test que sucursales están aisladas por empresa
     */
    public function test_branches_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin1 = User::factory()->create(['company_id' => $company1->id]);
        $admin2 = User::factory()->create(['company_id' => $company2->id]);

        $adminRole = Role::create(['name' => 'Admin']);
        $admin1->assignRole($adminRole);
        $admin2->assignRole($adminRole);

        Branch::factory()->count(3)->create(['company_id' => $company1->id, 'name' => 'Branch Company 1']);
        Branch::factory()->count(2)->create(['company_id' => $company2->id, 'name' => 'Branch Company 2']);

        $this->browse(function (Browser $browser) use ($admin1) {
            $browser->loginAs($admin1)
                    ->visit('/admin/branches')
                    ->assertSee('Branch Company 1')
                    ->assertDontSee('Branch Company 2');
        });
    }

    /**
     * Test que admin puede crear un departamento
     */
    public function test_admin_can_create_department(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin, $branch) {
            $browser->loginAs($admin)
                    ->visit('/admin/departments/create')
                    ->type('input[name="name"]', 'Departamento TI')
                    ->select('select[name="branch_id"]', $branch->id)
                    ->press('Crear')
                    ->pause(1000);

            $this->assertDatabaseHas('departments', [
                'name' => 'Departamento TI',
                'branch_id' => $branch->id,
            ]);
        });
    }

    /**
     * Test que departamento pertenece a una sucursal
     */
    public function test_department_belongs_to_branch(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'name' => 'Sucursal Norte']);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Recursos Humanos',
        ]);

        $this->assertEquals($branch->id, $department->branch_id);
        $this->assertEquals('Sucursal Norte', $department->branch->name);
    }

    /**
     * Test que sucursal puede tener múltiples departamentos
     */
    public function test_branch_can_have_multiple_departments(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        Department::factory()->count(4)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $this->assertEquals(4, Department::where('branch_id', $branch->id)->count());
    }

    /**
     * Test que departamentos están aislados por empresa
     */
    public function test_departments_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $branch1 = Branch::factory()->create(['company_id' => $company1->id]);
        $branch2 = Branch::factory()->create(['company_id' => $company2->id]);

        Department::factory()->count(3)->create(['company_id' => $company1->id, 'branch_id' => $branch1->id]);
        Department::factory()->count(2)->create(['company_id' => $company2->id, 'branch_id' => $branch2->id]);

        $this->assertEquals(3, Department::where('company_id', $company1->id)->count());
        $this->assertEquals(2, Department::where('company_id', $company2->id)->count());
    }

    /**
     * Test que documentos pueden filtrarse por sucursal
     */
    public function test_documents_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $branch1 = Branch::factory()->create(['company_id' => $company->id, 'name' => 'Sucursal A']);
        $branch2 = Branch::factory()->create(['company_id' => $company->id, 'name' => 'Sucursal B']);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'branch_id' => $branch1->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        Document::factory()->count(2)->create([
            'company_id' => $company->id,
            'branch_id' => $branch2->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals(3, Document::where('branch_id', $branch1->id)->count());
        $this->assertEquals(2, Document::where('branch_id', $branch2->id)->count());
    }

    /**
     * Test que usuarios pueden asignarse a sucursal y departamento
     */
    public function test_users_can_be_assigned_to_branch_and_department(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);

        $this->assertEquals($branch->id, $user->branch_id);
        $this->assertEquals($department->id, $user->department_id);
    }

    /**
     * Test que sucursal puede ser editada
     */
    public function test_branch_can_be_edited(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nombre Original',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $branch) {
            $browser->loginAs($admin)
                    ->visit('/admin/branches/' . $branch->id . '/edit')
                    ->clear('input[name="name"]')
                    ->type('input[name="name"]', 'Nombre Actualizado')
                    ->press('Guardar cambios')
                    ->pause(1000);

            $branch->refresh();
            $this->assertEquals('Nombre Actualizado', $branch->name);
        });
    }
}
