<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class SearchAndFilterTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected Company $company;

    protected Branch $branch;

    protected Department $department;

    protected User $user;

    protected Category $contractCategory;

    protected Category $invoiceCategory;

    protected Status $activeStatus;

    protected Status $archivedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->department = Department::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'email' => 'search-user@test.com',
        ]);
        $this->user->assignRole('regular_user');

        $this->activeStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Activo'],
        ]);

        $this->archivedStatus = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Archivado'],
        ]);

        $this->contractCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Contratos'],
        ]);

        $this->invoiceCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Facturas'],
        ]);

        $this->seedDocuments();
    }

    public function test_user_can_search_by_title(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->type('#search', 'Contrato')
                ->script("document.querySelector('form[action$=\"/documents\"]')?.submit();");

            $browser
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertSee('Contrato Archivado 2024')
                ->assertDontSee('Factura Enero 2024');
        });
    }

    public function test_user_can_search_by_document_number(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->type('#search', 'F-001')
                ->script("document.querySelector('form[action$=\"/documents\"]')?.submit();");

            $browser
                ->pause(500)
                ->assertSee('Factura Enero 2024')
                ->assertDontSee('Contrato de Servicio 2024');
        });
    }

    public function test_user_can_filter_by_category(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?category_id='.$this->contractCategory->id)
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertSee('Contrato Archivado 2024')
                ->assertDontSee('Factura Enero 2024');
        });
    }

    public function test_user_can_filter_by_status(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?status_id='.$this->archivedStatus->id)
                ->pause(500)
                ->assertSee('Contrato Archivado 2024')
                ->assertDontSee('Contrato de Servicio 2024')
                ->assertDontSee('Factura Enero 2024');
        });
    }

    public function test_user_can_filter_by_priority(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?priority=high')
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertDontSee('Factura Enero 2024')
                ->assertDontSee('Memorando Público');
        });
    }

    public function test_user_can_filter_by_confidentiality(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?is_confidential=1')
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertDontSee('Memorando Público');
        });
    }

    public function test_user_can_filter_by_date_range(): void
    {
        $from = now()->subDays(3)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($from, $to) {
            $browser->loginAs($this->user)
                ->visit('/documents?date_from='.$from.'&date_to='.$to)
                ->pause(500)
                ->assertSee('Memorando Público')
                ->assertDontSee('Contrato Archivado 2024');
        });
    }

    public function test_user_can_combine_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?search=Contrato&status_id='.$this->activeStatus->id.'&priority=high')
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertDontSee('Contrato Archivado 2024')
                ->assertDontSee('Factura Enero 2024');
        });
    }

    public function test_user_can_clear_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->type('#search', 'Contrato')
                ->select('#status_id', (string) $this->activeStatus->id)
                ->script("document.querySelector('form[action$=\"/documents\"]')?.submit();");

            $browser
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->click('a[href="https://archive-master-app.test/documents"]')
                ->pause(500)
                ->assertSee('Contrato de Servicio 2024')
                ->assertSee('Factura Enero 2024')
                ->assertSee('Contrato Archivado 2024');
        });
    }

    public function test_search_shows_empty_state_when_no_matches(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->type('#search', 'NoExiste-XYZ-999')
                ->script("document.querySelector('form[action$=\"/documents\"]')?.submit();");

            $browser
                ->pause(500)
                ->assertSee('No se encontraron documentos con los filtros aplicados');
        });
    }

    public function test_documents_page_shows_export_csv_when_results_exist(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->assertSee('Contrato de Servicio 2024')
                ->assertPresent('a[href*="/documents/export/csv"]');
        });
    }

    public function test_pagination_works_with_filters(): void
    {
        for ($index = 0; $index < 20; $index++) {
            Document::factory()->create([
                'company_id' => $this->company->id,
                'branch_id' => $this->branch->id,
                'department_id' => $this->department->id,
                'created_by' => $this->user->id,
                'assigned_to' => $this->user->id,
                'category_id' => $this->contractCategory->id,
                'status_id' => $this->activeStatus->id,
                'priority' => 'medium',
                'title' => 'Contrato Paginado '.$index,
                'document_number' => 'CP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'is_confidential' => false,
                'created_at' => now()->subMinutes($index + 1),
                'updated_at' => now()->subMinutes($index + 1),
            ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents?category_id='.$this->contractCategory->id)
                ->assertPresent('nav[role="navigation"]')
                ->assertPresent('a[href*="page=2"]')
                ->visit('/documents?category_id='.$this->contractCategory->id.'&page=2')
                ->pause(500)
                ->assertQueryStringHas('page', '2')
                ->assertQueryStringHas('category_id', (string) $this->contractCategory->id);
        });
    }

    public function test_empty_state_without_documents_is_shown(): void
    {
        Document::query()->delete();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/documents')
                ->assertSee('No tienes documentos')
                ->assertSee('Crear Documento');
        });
    }

    private function seedDocuments(): void
    {
        Document::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'title' => 'Contrato de Servicio 2024',
            'document_number' => 'CS-001',
            'description' => 'Contrato principal anual',
            'category_id' => $this->contractCategory->id,
            'status_id' => $this->activeStatus->id,
            'priority' => 'high',
            'is_confidential' => true,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'title' => 'Factura Enero 2024',
            'document_number' => 'F-001',
            'description' => 'Factura mensual',
            'category_id' => $this->invoiceCategory->id,
            'status_id' => $this->activeStatus->id,
            'priority' => 'medium',
            'is_confidential' => false,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'title' => 'Contrato Archivado 2024',
            'document_number' => 'CS-002',
            'description' => 'Contrato histórico',
            'category_id' => $this->contractCategory->id,
            'status_id' => $this->archivedStatus->id,
            'priority' => 'low',
            'is_confidential' => false,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'title' => 'Memorando Público',
            'document_number' => 'M-001',
            'description' => 'Comunicado general',
            'category_id' => $this->contractCategory->id,
            'status_id' => $this->activeStatus->id,
            'priority' => 'low',
            'is_confidential' => false,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }
}
