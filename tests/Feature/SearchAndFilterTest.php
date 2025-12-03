<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Models\Company;
use App\Models\Status;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $activeStatus;
    protected $archivedStatus;
    protected $category1;
    protected $category2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->activeStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Activo'],
        ]);

        $this->archivedStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Archivado'],
        ]);

        $this->category1 = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Contratos'],
        ]);

        $this->category2 = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Facturas'],
        ]);

        // Crear documentos de prueba
        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Contrato de Servicio 2024',
            'document_number' => 'CS-001',
            'category_id' => $this->category1->id,
            'status_id' => $this->activeStatus->id,
            'created_by' => $this->user->id,
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Factura Enero 2024',
            'document_number' => 'F-001',
            'category_id' => $this->category2->id,
            'status_id' => $this->activeStatus->id,
            'created_by' => $this->user->id,
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Contrato Archivado',
            'document_number' => 'CS-002',
            'category_id' => $this->category1->id,
            'status_id' => $this->archivedStatus->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test búsqueda por título
     */
    public function test_search_by_title()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents?search=Contrato');

        $response->assertStatus(200);
        $response->assertSee('Contrato de Servicio 2024');
        $response->assertSee('Contrato Archivado');
        $response->assertDontSee('Factura Enero 2024');
    }

    /**
     * Test búsqueda por código
     */
    public function test_search_by_code()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents?search=F-001');

        $response->assertStatus(200);
        $response->assertSee('Factura Enero 2024');
        $response->assertDontSee('Contrato de Servicio 2024');
    }

    /**
     * Test filtro por categoría
     */
    public function test_filter_by_category()
    {
        $response = $this->actingAs($this->user)
            ->get("/documents?category_id={$this->category1->id}");

        $response->assertStatus(200);
        $response->assertSee('Contrato de Servicio 2024');
        $response->assertSee('Contrato Archivado');
        $response->assertDontSee('Factura Enero 2024');
    }

    /**
     * Test filtro por estado
     */
    public function test_filter_by_status()
    {
        $response = $this->actingAs($this->user)
            ->get("/documents?status_id={$this->archivedStatus->id}");

        $response->assertStatus(200);
        $response->assertSee('Contrato Archivado');
        $response->assertDontSee('Contrato de Servicio 2024');
        $response->assertDontSee('Factura Enero 2024');
    }

    /**
     * Test combinación de múltiples filtros
     */
    public function test_combine_multiple_filters()
    {
        $response = $this->actingAs($this->user)
            ->get("/documents?search=Contrato&status_id={$this->activeStatus->id}");

        $response->assertStatus(200);
        $response->assertSee('Contrato de Servicio 2024');
        $response->assertDontSee('Contrato Archivado');
        $response->assertDontSee('Factura Enero 2024');
    }

    /**
     * Test exportar documentos a CSV
     */
    public function test_export_documents_to_csv()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents/export/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test paginación de resultados
     */
    public function test_pagination_works()
    {
        // Crear 25 documentos adicionales
        Document::factory()->count(25)->create([
            'company_id' => $this->company->id,
            'status_id' => $this->activeStatus->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/documents');

        $response->assertStatus(200);
        $response->assertViewHas('documents');

        $documents = $response->viewData('documents');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $documents);
    }

    /**
     * Test búsqueda sin resultados
     */
    public function test_search_returns_no_results_when_no_matches()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents?search=NoExiste12345');

        $response->assertStatus(200);
        $response->assertSee('No se encontraron documentos');
    }

    /**
     * Test filtro por rango de fechas
     */
    public function test_filter_by_date_range()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents?date_from=' . now()->subDays(7)->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'));

        $response->assertStatus(200);
    }

    /**
     * Test ordenamiento de resultados
     */
    public function test_sorting_works()
    {
        $response = $this->actingAs($this->user)
            ->get('/documents?sort=title&order=asc');

        $response->assertStatus(200);

        // Verificar que la respuesta tiene documentos
        if ($response->viewData('documents')) {
            $documents = $response->viewData('documents');
            $this->assertGreaterThan(0, $documents->count());
        }
    }
}
