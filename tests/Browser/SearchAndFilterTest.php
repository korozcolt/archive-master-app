<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Document;
use App\Models\Company;
use App\Models\Status;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SearchAndFilterTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $company;
    protected $activeStatus;
    protected $archivedStatus;
    protected $type1;
    protected $type2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'user@test.com',
        ]);

        $this->activeStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Activo'],
        ]);

        $this->archivedStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Archivado'],
        ]);

        $this->type1 = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Contrato'],
        ]);

        $this->type2 = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Factura'],
        ]);

        // Crear documentos de prueba
        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Contrato de Servicio 2024',
            'document_number' => 'CS-001',
            'category_id' => $this->type1->id,
            'status_id' => $this->activeStatus->id,
            'created_by' => $this->user->id,
            'due_at' => now()->addDays(10),
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Factura Enero 2024',
            'document_number' => 'F-001',
            'category_id' => $this->type2->id,
            'status_id' => $this->activeStatus->id,
            'created_by' => $this->user->id,
            'due_at' => now()->addDays(30),
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Contrato Archivado',
            'document_number' => 'CS-002',
            'category_id' => $this->type1->id,
            'status_id' => $this->archivedStatus->id,
            'created_by' => $this->user->id,
            'due_at' => now()->subDays(5),
        ]);
    }

    /**
     * Test que el usuario puede buscar documentos por título
     */
    public function testUserCanSearchByTitle()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'Contrato')
                    ->pause(500) // Esperar búsqueda
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertSee('Contrato Archivado')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que el usuario puede buscar por código
     */
    public function testUserCanSearchByCode()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'F-001')
                    ->pause(500)
                    ->assertSee('Factura Enero 2024')
                    ->assertDontSee('Contrato de Servicio 2024');
        });
    }

    /**
     * Test que el usuario puede filtrar por tipo de documento
     */
    public function testUserCanFilterByCategory()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->select('category_id', $this->type1->id)
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertSee('Contrato Archivado')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que el usuario puede filtrar por estado
     */
    public function testUserCanFilterByStatus()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->select('status_id', $this->archivedStatus->id)
                    ->pause(500)
                    ->assertSee('Contrato Archivado')
                    ->assertDontSee('Contrato de Servicio 2024')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que el usuario puede filtrar por rango de fechas
     */
    public function testUserCanFilterByDateRange()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('date_from', now()->format('Y-m-d'))
                    ->type('date_to', now()->addDays(15)->format('Y-m-d'))
                    ->press('Buscar')
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que el usuario puede combinar múltiples filtros
     */
    public function testUserCanCombineMultipleFilters()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'Contrato')
                    ->select('status_id', $this->activeStatus->id)
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertDontSee('Contrato Archivado')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que el usuario puede limpiar filtros
     */
    public function testUserCanClearFilters()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'Contrato')
                    ->select('status_id', $this->activeStatus->id)
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->press('Limpiar filtros')
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertSee('Factura Enero 2024')
                    ->assertSee('Contrato Archivado');
        });
    }

    /**
     * Test que el usuario puede exportar resultados a CSV
     */
    public function testUserCanExportToCSV()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'Contrato')
                    ->press('Exportar CSV')
                    ->pause(1000);

            // Verificar que se descargó el archivo
            $this->assertFileExists(
                $browser->driver->getDownloadDirectory() . '/documents_export_*.csv'
            );
        });
    }

    /**
     * Test que la búsqueda no retorna resultados cuando no hay coincidencias
     */
    public function testSearchReturnsNoResultsWhenNoMatches()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', 'NoExiste12345')
                    ->pause(500)
                    ->assertSee('No se encontraron documentos');
        });
    }

    /**
     * Test que la paginación funciona con filtros aplicados
     */
    public function testPaginationWorksWithFilters()
    {
        // Crear documentos adicionales para probar paginación
        for ($i = 0; $i < 20; $i++) {
            Document::factory()->create([
                'company_id' => $this->company->id,
                'title' => 'Contrato ' . $i,
                'document_number' => 'C-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'category_id' => $this->type1->id,
                'status_id' => $this->activeStatus->id,
                'created_by' => $this->user->id,
            ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->select('category_id', $this->type1->id)
                    ->pause(500)
                    ->assertPresent('.pagination')
                    ->click('.pagination a[rel="next"]')
                    ->pause(500)
                    ->assertPresent('.document-item');
        });
    }

    /**
     * Test que el ordenamiento funciona correctamente
     */
    public function testSortingWorks()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->click('th[data-sort="title"]')
                    ->pause(500)
                    ->assertSeeIn('tbody tr:first-child', 'Contrato')
                    ->click('th[data-sort="title"]')
                    ->pause(500)
                    ->assertSeeIn('tbody tr:first-child', 'Factura');
        });
    }

    /**
     * Test que el filtro de documentos próximos a vencer funciona
     */
    public function testExpiringDocumentsFilter()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->click('input[name="expiring_soon"]')
                    ->pause(500)
                    ->assertSee('Contrato de Servicio 2024')
                    ->assertDontSee('Factura Enero 2024');
        });
    }

    /**
     * Test que se pueden ver documentos vencidos
     */
    public function testExpiredDocumentsFilter()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->click('input[name="expired"]')
                    ->pause(500)
                    ->assertSee('Contrato Archivado');
        });
    }
}
