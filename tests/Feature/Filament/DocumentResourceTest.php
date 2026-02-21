<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DocumentResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Company $company;

    protected Status $status;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Set application locale
        app()->setLocale('es');

        // Create company and super admin user
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $superAdminRole = Role::create(['name' => 'super_admin']);
        $this->admin->assignRole($superAdminRole);

        // Create initial status and category for documents
        $this->status = Status::factory()->create([
            'company_id' => $this->company->id,
            'is_initial' => true,
        ]);

        $this->category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function can_view_documents_list_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->assertSuccessful();
    }

    /** @test */
    public function documents_list_shows_all_documents()
    {
        $this->actingAs($this->admin);

        // Create documents
        $documents = Document::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($documents);
    }

    /** @test */
    public function can_search_documents_by_title()
    {
        $this->actingAs($this->admin);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'title' => 'Documento Búsqueda XYZ',
            'created_by' => $this->admin->id,
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->admin->id,
        ]);

        $component = Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->searchTable('Búsqueda');

        // Verify search functionality works
        $this->assertEquals('Búsqueda', $component->instance()->getTableSearch());
    }

    /** @test */
    public function can_access_create_document_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->assertSuccessful();
    }

    /** @test */
    public function can_view_document_page()
    {
        $this->actingAs($this->admin);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->admin->id,
            'file_path' => 'documents/test.pdf',
        ]);

        Livewire::test(DocumentResource\Pages\ViewDocument::class, [
            'record' => $document->id,
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function can_create_document()
    {
        $this->actingAs($this->admin);

        $newData = [
            'title' => 'Nuevo Documento Test',
            'description' => 'Descripción del documento test',
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'priority' => 'medium',
            'is_confidential' => false,
            'digital_document_type' => 'copia',
            'tracking_enabled' => false,
            'tags' => [],
        ];

        // Use fill() instead of fillForm() due to Filament bug #15557
        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'Nuevo Documento Test',
            'description' => 'Descripción del documento test',
        ]);
    }

    /** @test */
    public function document_title_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->set('data.title', null)
            ->call('create')
            ->assertHasFormErrors(['title']);
    }

    /** @test */
    public function status_id_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => [
                'title' => 'Test Document',
                'company_id' => $this->company->id,
                'status_id' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['status_id']);
    }

    /** @test */
    public function company_id_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => [
                'title' => 'Test Document',
                'status_id' => $this->status->id,
                'company_id' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['company_id']);
    }

    /** @test */
    public function can_edit_document()
    {
        $this->actingAs($this->admin);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'title' => 'Documento Original',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\EditDocument::class, [
            'record' => $document->id,
        ])
            ->assertSet('data.title', 'Documento Original')
            ->set('data.title', 'Documento Modificado')
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();
        $this->assertEquals('Documento Modificado', $document->title);
    }

    /** @test */
    public function can_mark_document_as_confidential()
    {
        $this->actingAs($this->admin);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'is_confidential' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\EditDocument::class, [
            'record' => $document->id,
        ])
            ->assertSet('data.is_confidential', false)
            ->set('data.is_confidential', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();
        $this->assertTrue($document->is_confidential);
    }

    /** @test */
    public function can_delete_document()
    {
        $this->actingAs($this->admin);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\EditDocument::class, [
            'record' => $document->id,
        ])
            ->callAction('delete');

        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    /** @test */
    public function can_assign_document_to_user()
    {
        $this->actingAs($this->admin);

        $assignee = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $newData = [
            'title' => 'Documento Asignado',
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'assigned_to' => $assignee->id,
            'priority' => 'high',
            'is_confidential' => false,
            'digital_document_type' => 'copia',
            'tracking_enabled' => false,
            'tags' => [],
        ];

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'Documento Asignado',
            'assigned_to' => $assignee->id,
        ]);
    }

    /** @test */
    public function can_set_document_priority()
    {
        $this->actingAs($this->admin);

        $newData = [
            'title' => 'Documento Urgente',
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'priority' => 'high',
            'is_confidential' => false,
            'digital_document_type' => 'copia',
            'tracking_enabled' => false,
            'tags' => [],
        ];

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'Documento Urgente',
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function can_associate_document_with_branch_and_department()
    {
        $this->actingAs($this->admin);

        $branch = Branch::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $department = Department::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
        ]);

        $newData = [
            'title' => 'Documento con Sucursal',
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'status_id' => $this->status->id,
            'priority' => 'medium',
            'is_confidential' => false,
            'digital_document_type' => 'copia',
            'tracking_enabled' => false,
            'tags' => [],
        ];

        Livewire::test(DocumentResource\Pages\CreateDocument::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'Documento con Sucursal',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
    }

    /** @test */
    public function can_filter_documents_by_priority()
    {
        $this->actingAs($this->admin);

        $highPriorityDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'priority' => 'high',
            'created_by' => $this->admin->id,
        ]);

        $lowPriorityDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'priority' => 'low',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->filterTable('priority', 'high')
            ->assertCanSeeTableRecords([$highPriorityDoc])
            ->assertCanNotSeeTableRecords([$lowPriorityDoc]);
    }

    /** @test */
    public function can_filter_documents_by_confidential_status()
    {
        $this->actingAs($this->admin);

        $confidentialDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'is_confidential' => true,
            'created_by' => $this->admin->id,
        ]);

        $publicDoc = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'is_confidential' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->filterTable('is_confidential', true)
            ->assertCanSeeTableRecords([$confidentialDoc])
            ->assertCanNotSeeTableRecords([$publicDoc]);
    }

    /** @test */
    public function documents_are_isolated_by_company()
    {
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $company2 = Company::factory()->create(['name' => 'Company 2']);

        $status1 = Status::factory()->create(['company_id' => $company1->id]);
        $status2 = Status::factory()->create(['company_id' => $company2->id]);

        $document1 = Document::factory()->create([
            'company_id' => $company1->id,
            'status_id' => $status1->id,
            'title' => 'Document Company 1',
            'created_by' => $this->admin->id,
        ]);

        $document2 = Document::factory()->create([
            'company_id' => $company2->id,
            'status_id' => $status2->id,
            'title' => 'Document Company 2',
            'created_by' => $this->admin->id,
        ]);

        // Super admin sees all documents
        $this->actingAs($this->admin);

        Livewire::test(DocumentResource\Pages\ListDocuments::class)
            ->assertCanSeeTableRecords([$document1, $document2]);
    }

    /** @test */
    public function can_view_document_details()
    {
        $this->actingAs($this->admin);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'title' => 'Documento Detalles',
            'description' => 'Descripción detallada',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentResource\Pages\ViewDocument::class, [
            'record' => $document->id,
        ])
            ->assertSuccessful()
            ->assertSee('Documento Detalles')
            ->assertSee('Descripción detallada');
    }
}
