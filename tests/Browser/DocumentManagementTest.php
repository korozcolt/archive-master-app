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

class DocumentManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que un usuario puede ver la lista de documentos
     */
    public function test_user_can_view_documents_list(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        // Crear rol admin
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear algunos documentos
        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents')
                ->assertSee('Documentos')
                ->assertPresent('table');
        });
    }

    /**
     * Test que un usuario puede buscar documentos
     */
    public function test_user_can_search_documents(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear documento específico
        Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'Documento de Prueba Único',
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents')
                ->type('input[type="search"]', 'Único')
                ->pause(1000) // Wait for search results
                ->assertSee('Documento de Prueba Único');
        });
    }

    /**
     * Test que un usuario puede acceder a crear un documento
     */
    public function test_user_can_access_create_document_page(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->assertPathIs('/admin/documents/create')
                ->assertSee('Crear')
                ->assertSee('documento');
        });
    }

    /**
     * Test que un usuario puede filtrar documentos por estado
     */
    public function test_user_can_filter_documents_by_status(): void
    {
        $company = Company::factory()->create();
        $status1 = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Borrador',
        ]);
        $status2 = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Aprobado',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear documentos con diferentes estados
        Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'status_id' => $status1->id,
            'title' => 'Documento en Borrador',
        ]);

        Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'status_id' => $status2->id,
            'title' => 'Documento Aprobado',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents')
                ->assertSee('Documento en Borrador')
                ->assertSee('Documento Aprobado')
                ->click('button[title="Filtrar"]')
                ->waitFor('.fi-ta-filters')
                ->pause(500);
        });
    }
}
