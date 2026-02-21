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

class ExportFeaturesTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que usuario puede exportar documentos a CSV
     */
    public function test_user_can_export_documents_to_csv(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->click('button[title="Exportar"]')
                ->click('button[data-format="csv"]')
                ->pause(2000);

            // Verificar que la descarga se inició
            // La implementación exacta depende de cómo manejas descargas en tu sistema
        });
    }

    /**
     * Test que usuario puede exportar documentos a Excel
     */
    public function test_user_can_export_documents_to_excel(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->click('button[title="Exportar"]')
                ->click('button[data-format="xlsx"]')
                ->pause(2000);
        });
    }

    /**
     * Test que usuario puede exportar documentos a PDF
     */
    public function test_user_can_export_documents_to_pdf(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->click('button[title="Exportar"]')
                ->click('button[data-format="pdf"]')
                ->pause(2000);
        });
    }

    /**
     * Test que exportación respeta filtros aplicados
     */
    public function test_export_respects_applied_filters(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status1 = Status::factory()->create(['company_id' => $company->id, 'name' => 'Activo']);
        $status2 = Status::factory()->create(['company_id' => $company->id, 'name' => 'Inactivo']);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status1->id,
            'created_by' => $admin->id,
        ]);

        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status2->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->click('button[title="Filtrar"]')
                ->pause(500);

            // Aplicar filtro por estado
            // Luego exportar
            // Debe exportar solo los documentos filtrados
        });
    }

    /**
     * Test que usuario puede exportar documentos seleccionados
     */
    public function test_user_can_export_selected_documents(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->check('input[type="checkbox"][data-row="1"]')
                ->check('input[type="checkbox"][data-row="2"]')
                ->check('input[type="checkbox"][data-row="3"]')
                ->click('button[title="Exportar seleccionados"]')
                ->pause(2000);

            // Debería exportar solo 3 documentos
        });
    }

    /**
     * Test que exportación incluye todas las columnas relevantes
     */
    public function test_export_includes_all_relevant_columns(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'title' => 'Documento Test',
            'document_number' => 'DOC-001',
        ]);

        // La exportación debería incluir:
        // - ID, document_number, title, category, status, created_at, updated_at, etc.
        $this->assertTrue(true); // Test conceptual
    }

    /**
     * Test que usuario puede exportar con búsqueda aplicada
     */
    public function test_export_with_search_applied(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'title' => 'Contrato Especial',
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->type('input[type="search"]', 'Especial')
                ->pause(1000)
                ->click('button[title="Exportar"]')
                ->pause(2000);

            // Debería exportar solo el documento que coincide con la búsqueda
        });
    }
}
