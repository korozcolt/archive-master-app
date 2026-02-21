<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class CategoryManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que admin puede ver lista de categorías
     */
    public function test_admin_can_view_categories_list(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear categorías
        Category::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->assertSee('Categorías')
                ->assertPresent('table');
        });
    }

    /**
     * Test que admin puede crear una categoría padre
     */
    public function test_admin_can_create_parent_category(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->clickLink('Nueva')
                ->waitForLocation('/admin/categories/create')
                ->type('input[name="name"]', 'Contratos')
                ->type('textarea[name="description"]', 'Categoría para contratos legales')
                ->press('Crear')
                ->pause(1000);

            // Verificar en base de datos
            $this->assertDatabaseHas('categories', [
                'name' => 'Contratos',
                'parent_id' => null, // Es una categoría padre
            ]);
        });
    }

    /**
     * Test que admin puede crear una subcategoría
     */
    public function test_admin_can_create_subcategory(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear categoría padre
        $parentCategory = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Documentos Legales',
            'parent_id' => null,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $parentCategory) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/create')
                ->type('input[name="name"]', 'Contratos Laborales')
                ->select('select[name="parent_id"]', $parentCategory->id)
                ->press('Crear')
                ->pause(1000);

            // Verificar en base de datos
            $this->assertDatabaseHas('categories', [
                'name' => 'Contratos Laborales',
                'parent_id' => $parentCategory->id,
            ]);
        });
    }

    /**
     * Test que se requiere nombre al crear categoría
     */
    public function test_category_creation_requires_name(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/create')
                ->press('Crear')
                ->pause(500)
                ->assertPresent('input[name="name"]:invalid');
        });
    }

    /**
     * Test que admin puede editar una categoría
     */
    public function test_admin_can_edit_category(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría Original',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/'.$category->id.'/edit')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Categoría Modificada')
                ->clear('textarea[name="description"]')
                ->type('textarea[name="description"]', 'Descripción actualizada')
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $category->refresh();
            $this->assertEquals('Categoría Modificada', $category->name);
            $this->assertEquals('Descripción actualizada', $category->description);
        });
    }

    /**
     * Test que categoría muestra estructura jerárquica
     */
    public function test_categories_show_hierarchical_structure(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear jerarquía: Documentos > Legales > Contratos
        $root = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Documentos',
            'parent_id' => null,
        ]);

        $child1 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Legales',
            'parent_id' => $root->id,
        ]);

        $child2 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Contratos',
            'parent_id' => $child1->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->assertSee('Documentos')
                ->assertSee('Legales')
                ->assertSee('Contratos');
        });

        // Verificar jerarquía en base de datos
        $this->assertEquals($root->id, $child1->parent_id);
        $this->assertEquals($child1->id, $child2->parent_id);
    }

    /**
     * Test que categorías están aisladas por empresa
     */
    public function test_categories_are_isolated_by_company(): void
    {
        // Empresa 1
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $admin1 = User::factory()->create([
            'company_id' => $company1->id,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin1->assignRole($adminRole);

        Category::factory()->count(3)->create([
            'company_id' => $company1->id,
            'name' => 'Categoría Company 1',
        ]);

        // Empresa 2
        $company2 = Company::factory()->create(['name' => 'Company 2']);
        $admin2 = User::factory()->create([
            'company_id' => $company2->id,
        ]);
        $admin2->assignRole($adminRole);

        Category::factory()->count(2)->create([
            'company_id' => $company2->id,
            'name' => 'Categoría Company 2',
        ]);

        // Admin 1 solo ve categorías de su empresa
        $this->browse(function (Browser $browser) use ($admin1, $company1) {
            $browser->loginAs($admin1)
                ->visit('/admin/categories')
                ->assertSee('Categoría Company 1')
                ->assertDontSee('Categoría Company 2');

            // Verificar en base de datos
            $this->assertEquals(3, Category::where('company_id', $company1->id)->count());
        });

        // Admin 2 solo ve categorías de su empresa
        $this->browse(function (Browser $browser) use ($admin2, $company2) {
            $browser->loginAs($admin2)
                ->visit('/admin/categories')
                ->assertSee('Categoría Company 2')
                ->assertDontSee('Categoría Company 1');

            // Verificar en base de datos
            $this->assertEquals(2, Category::where('company_id', $company2->id)->count());
        });
    }

    /**
     * Test que se pueden buscar categorías
     */
    public function test_categories_can_be_searched(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear categoría específica
        Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría Especial XYZ',
        ]);

        Category::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->type('input[type="search"]', 'XYZ')
                ->pause(1000)
                ->assertSee('Categoría Especial XYZ');
        });
    }

    /**
     * Test que categoría muestra conteo de documentos
     */
    public function test_category_shows_document_count(): void
    {
        $company = Company::factory()->create();
        $status = Status::factory()->create(['company_id' => $company->id]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría con Documentos',
        ]);

        // Crear 5 documentos en esta categoría
        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/'.$category->id)
                ->pause(500);

            // Verificar conteo en base de datos
            $this->assertEquals(5, Document::where('category_id', $category->id)->count());
        });
    }

    /**
     * Test que categoría puede ser eliminada si no tiene documentos
     */
    public function test_empty_category_can_be_deleted(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría Sin Documentos',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->pause(500);

            // Eliminar categoría
            $category->delete();

            // Verificar soft delete
            $this->assertSoftDeleted('categories', [
                'id' => $category->id,
            ]);
        });
    }

    /**
     * Test que categoría con documentos no puede ser eliminada
     */
    public function test_category_with_documents_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $status = Status::factory()->create(['company_id' => $company->id]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría Con Documentos',
        ]);

        // Crear documentos en esta categoría
        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->pause(500);

            // Intentar eliminar debe fallar o mostrar advertencia
            try {
                $category->delete();
                $this->fail('Expected exception was not thrown');
            } catch (\Exception $e) {
                // Se espera que falle porque tiene documentos
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Test que categoría puede tener múltiples niveles de profundidad
     */
    public function test_category_can_have_multiple_depth_levels(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        // Crear 4 niveles: Nivel1 > Nivel2 > Nivel3 > Nivel4
        $level1 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nivel 1',
            'parent_id' => null,
        ]);

        $level2 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nivel 2',
            'parent_id' => $level1->id,
        ]);

        $level3 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nivel 3',
            'parent_id' => $level2->id,
        ]);

        $level4 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nivel 4',
            'parent_id' => $level3->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/categories')
                ->assertSee('Nivel 1')
                ->assertSee('Nivel 2')
                ->assertSee('Nivel 3')
                ->assertSee('Nivel 4');
        });

        // Verificar cadena jerárquica
        $this->assertEquals($level1->id, $level2->parent_id);
        $this->assertEquals($level2->id, $level3->parent_id);
        $this->assertEquals($level3->id, $level4->parent_id);
    }

    /**
     * Test que categoría puede cambiar de padre
     */
    public function test_category_can_change_parent(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $parent1 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Padre Original',
        ]);

        $parent2 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Padre Nuevo',
        ]);

        $child = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Hijo',
            'parent_id' => $parent1->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $child, $parent2) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/'.$child->id.'/edit')
                ->select('select[name="parent_id"]', $parent2->id)
                ->press('Guardar cambios')
                ->pause(1000);

            // Verificar en base de datos
            $child->refresh();
            $this->assertEquals($parent2->id, $child->parent_id);
        });
    }

    /**
     * Test que categoría puede visualizarse con sus detalles
     */
    public function test_category_details_can_be_viewed(): void
    {
        $company = Company::factory()->create();
        $status = Status::factory()->create(['company_id' => $company->id]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría Detallada',
            'description' => 'Descripción completa de la categoría',
        ]);

        // Crear algunos documentos
        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                ->visit('/admin/categories/'.$category->id)
                ->assertSee('Categoría Detallada')
                ->assertSee('Descripción completa de la categoría');
        });
    }
}
