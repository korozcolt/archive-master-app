<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Category;
use App\Models\Status;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class AdvancedSearchTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que un usuario puede acceder a la búsqueda avanzada
     */
    public function test_user_can_access_advanced_search(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin/advanced-searches')
                    ->assertSee('Búsqueda')
                    ->pause(500);
        });
    }

    /**
     * Test que la búsqueda simple funciona en documentos
     */
    public function test_simple_search_works_in_documents(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear documentos de prueba
        $targetDoc = Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'title' => 'Contrato Especial XYZ123',
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $targetDoc) {
            $browser->loginAs($user)
                    ->visit('/admin/documents')
                    ->type('input[type="search"]', 'XYZ123')
                    ->pause(1000)
                    ->assertSee('Contrato Especial XYZ123');
        });
    }

    /**
     * Test que se pueden filtrar documentos por categoría
     */
    public function test_user_can_filter_by_category(): void
    {
        $company = Company::factory()->create();
        $category1 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Contratos',
        ]);
        $category2 = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Facturas',
        ]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear documentos en diferentes categorías
        Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'category_id' => $category1->id,
            'status_id' => $status->id,
            'title' => 'Contrato de Trabajo',
        ]);

        Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'category_id' => $category2->id,
            'status_id' => $status->id,
            'title' => 'Factura de Compra',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin/documents')
                    ->assertSee('Contrato de Trabajo')
                    ->assertSee('Factura de Compra')
                    ->pause(500);
        });
    }

    /**
     * Test que se muestra mensaje cuando no hay resultados
     */
    public function test_no_results_message_appears_when_search_returns_nothing(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin/documents')
                    ->type('input[type="search"]', 'DocumentoQueNoExiste123456789')
                    ->pause(1000)
                    ->assertDontSee('DocumentoQueNoExiste123456789');
        });
    }
}
