<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentUploadDraft;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $company;

    protected $status;

    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->status = Status::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Test que un usuario puede ver la lista de documentos
     */
    public function test_user_can_view_documents_list()
    {
        Document::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/documents');

        $response->assertStatus(200);
        $response->assertViewIs('documents.index');
    }

    /**
     * Test que un usuario puede crear un documento
     */
    public function test_user_can_create_document()
    {
        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Test Document',
                'document_number' => 'TEST-001',
                'description' => 'Test description',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'title' => 'Test Document',
            'document_number' => 'TEST-001',
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test que un usuario puede subir un archivo y se guarda en file_path
     */
    public function test_user_can_upload_document_file()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Documento con archivo',
                'document_number' => 'FILE-001',
                'description' => 'Documento con archivo adjunto',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
                'file' => $file,
            ]);

        $response->assertRedirect();

        $document = Document::where('document_number', 'FILE-001')->firstOrFail();

        expect($document->file_path)->not->toBeNull();
        Storage::disk('local')->assertExists($document->file_path);
    }

    /**
     * Test que un usuario puede crear varios documentos en lote con carga múltiple
     */
    public function test_user_can_create_documents_in_bulk_upload()
    {
        Storage::fake('local');

        $fileOne = UploadedFile::fake()->create('contrato_proveedor.pdf', 120, 'application/pdf');
        $fileTwo = UploadedFile::fake()->create('informe_gestion.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'description' => 'Carga masiva de prueba',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
                'files' => [$fileOne, $fileTwo],
                'bulk_items' => [
                    ['title' => 'Contrato Proveedor 2026'],
                    ['title' => 'Informe de Gestión Enero'],
                ],
            ]);

        $response->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'title' => 'Contrato Proveedor 2026',
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Informe de Gestión Enero',
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);

        expect(Document::query()->where('created_by', $this->user->id)->count())->toBe(2);
    }

    public function test_user_can_override_category_and_status_per_file_in_bulk_upload()
    {
        Storage::fake('local');

        $categoryOne = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Contratos', 'en' => 'Contracts'],
        ]);
        $categoryTwo = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Informes', 'en' => 'Reports'],
        ]);

        $statusOne = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Recibido', 'en' => 'Received'],
        ]);
        $statusTwo = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Borrador', 'en' => 'Draft'],
        ]);

        $fileOne = UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf');
        $fileTwo = UploadedFile::fake()->create('informe.pdf', 140, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'description' => 'Lote con metadata por archivo',
                'category_id' => $this->category->id, // valor por defecto del lote
                'status_id' => $this->status->id, // valor por defecto del lote
                'priority' => 'medium',
                'files' => [$fileOne, $fileTwo],
                'bulk_items' => [
                    [
                        'title' => 'Contrato Cliente',
                        'category_id' => $categoryOne->id,
                        'status_id' => $statusOne->id,
                    ],
                    [
                        'title' => 'Informe Trimestral',
                        'category_id' => $categoryTwo->id,
                        'status_id' => $statusTwo->id,
                    ],
                ],
            ]);

        $response->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'title' => 'Contrato Cliente',
            'category_id' => $categoryOne->id,
            'status_id' => $statusOne->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Informe Trimestral',
            'category_id' => $categoryTwo->id,
            'status_id' => $statusTwo->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_user_can_upload_temp_file_and_save_draft()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('acta-reunion.pdf', 120, 'application/pdf');

        $uploadResponse = $this->actingAs($this->user)
            ->postJson(route('documents.upload-drafts.temp-file'), [
                'file' => $file,
            ]);

        $uploadResponse->assertOk();
        $draftId = (int) $uploadResponse->json('draft_id');
        $itemId = (int) $uploadResponse->json('item.id');

        expect($draftId)->toBeGreaterThan(0);
        expect($itemId)->toBeGreaterThan(0);

        $saveResponse = $this->actingAs($this->user)
            ->postJson(route('documents.upload-drafts.save'), [
                'draft_id' => $draftId,
                'current_step' => 2,
                'description' => 'Borrador de carga',
                'category_id' => $this->category->id,
                'status_id' => $this->status->id,
                'priority' => 'medium',
                'items' => [
                    [
                        'id' => $itemId,
                        'title' => 'Acta de Reunión Comercial',
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $saveResponse->assertOk()->assertJsonPath('draft.id', $draftId);

        $this->assertDatabaseHas('document_upload_drafts', [
            'id' => $draftId,
            'user_id' => $this->user->id,
            'status' => 'draft',
            'description' => 'Borrador de carga',
        ]);

        $this->assertDatabaseHas('document_upload_draft_items', [
            'id' => $itemId,
            'document_upload_draft_id' => $draftId,
            'title' => 'Acta de Reunión Comercial',
        ]);
    }

    public function test_user_can_create_documents_from_saved_draft_with_temp_files()
    {
        Storage::fake('local');

        $fileOne = UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf');
        $fileTwo = UploadedFile::fake()->create('informe.pdf', 90, 'application/pdf');

        $first = $this->actingAs($this->user)->postJson(route('documents.upload-drafts.temp-file'), ['file' => $fileOne]);
        $draftId = (int) $first->json('draft_id');
        $itemOneId = (int) $first->json('item.id');

        $second = $this->actingAs($this->user)->postJson(route('documents.upload-drafts.temp-file'), [
            'file' => $fileTwo,
            'draft_id' => $draftId,
        ]);
        $itemTwoId = (int) $second->json('item.id');

        $statusOverride = Status::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->post('/documents', [
            'draft_id' => $draftId,
            'description' => 'Carga desde borrador',
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'priority' => 'high',
            'bulk_items' => [
                ['id' => $itemOneId, 'title' => 'Contrato Cliente'],
                ['id' => $itemTwoId, 'title' => 'Informe Comercial', 'status_id' => $statusOverride->id],
            ],
        ]);

        $response->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'title' => 'Contrato Cliente',
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Informe Comercial',
            'company_id' => $this->company->id,
            'status_id' => $statusOverride->id,
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('document_upload_drafts', [
            'id' => $draftId,
            'status' => 'submitted',
        ]);

        /** @var DocumentUploadDraft $draft */
        $draft = DocumentUploadDraft::query()->with('items')->findOrFail($draftId);
        foreach ($draft->items as $item) {
            if ($item->temp_path) {
                Storage::disk($item->temp_disk)->assertMissing($item->temp_path);
            }
        }
    }

    /**
     * Test que un usuario puede ver un documento
     */
    public function test_user_can_view_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/documents/{$document->id}");

        $response->assertStatus(200);
        $response->assertViewIs('documents.show');
    }

    public function test_document_activity_log_shows_human_readable_values()
    {
        $assignee = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Gloria Recepción',
        ]);

        $status = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Aprobado', 'en' => 'Approved'],
        ]);

        $category = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Contratos', 'en' => 'Contracts'],
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $status->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $assignee->id,
            'priority' => 'high',
        ]);

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'updated',
            'subject_type' => Document::class,
            'subject_id' => $document->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'event' => 'updated',
            'properties' => [
                'old' => [
                    'priority' => 'medium',
                    'status_id' => $this->status->id,
                    'category_id' => $this->category->id,
                    'assigned_to' => $this->user->id,
                ],
                'attributes' => [
                    'priority' => 'high',
                    'status_id' => $status->id,
                    'category_id' => $category->id,
                    'assigned_to' => $assignee->id,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)->get("/documents/{$document->id}");

        $response->assertOk();
        $response->assertSee('Prioridad:');
        $response->assertSee('Media');
        $response->assertSee('Alta');
        $response->assertSee('Estado:');
        $response->assertSee('Aprobado');
        $response->assertSee('Categoría:');
        $response->assertSee('Contratos');
        $response->assertSee('Asignado a:');
        $response->assertSee('Gloria Recepción');
    }

    public function test_document_activity_log_hides_empty_updated_noise_entries()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'updated',
            'subject_type' => Document::class,
            'subject_id' => $document->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'event' => 'updated',
            'properties' => ['changes' => ['status' => ['old' => 1, 'new' => 2]]],
        ]);

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'updated',
            'subject_type' => Document::class,
            'subject_id' => $document->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'event' => 'updated',
            'properties' => [
                'old' => ['priority' => 'medium'],
                'attributes' => ['priority' => 'high'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get("/documents/{$document->id}");

        $response->assertOk();

        $html = $response->getContent();
        expect(substr_count($html, 'Se actualizaron datos del documento.'))->toBe(1);
        $response->assertSee('Prioridad:');
        $response->assertSee('Media');
        $response->assertSee('Alta');
    }

    /**
     * Test que un usuario puede actualizar un documento
     */
    public function test_user_can_update_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/documents/{$document->id}", [
                'title' => 'Updated Title',
                'document_number' => $document->document_number,
                'description' => 'Updated description',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'high',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    public function test_edit_document_view_shows_translated_labels_and_portal_style_sections()
    {
        app()->setLocale('es');

        $category = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Documentos Legales', 'en' => 'Legal Documents'],
        ]);

        $status = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Borrador', 'en' => 'Draft'],
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'file_path' => 'documents/test-file.pdf',
        ]);

        $response = $this->actingAs($this->user)->get("/documents/{$document->id}/edit");

        $response->assertOk();
        $response->assertViewIs('documents.edit');
        $response->assertSee('Editar Documento');
        $response->assertSee('Adjunto');
        $response->assertSee('Opciones');
        $response->assertSee('Documentos Legales');
        $response->assertSee('Borrador');
        $response->assertDontSee('{"es":"Documentos Legales"}');
        $response->assertDontSee('{"es":"Borrador"}');
    }

    /**
     * Test que un usuario puede eliminar un documento
     */
    public function test_user_can_delete_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/documents/{$document->id}");

        $response->assertRedirect();

        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    /**
     * Test que un usuario no puede ver documentos de otra empresa
     */
    public function test_user_cannot_view_other_company_documents()
    {
        $otherCompany = Company::factory()->create();
        $otherStatus = Status::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);

        $document = Document::factory()->create([
            'company_id' => $otherCompany->id,
            'status_id' => $otherStatus->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/documents/{$document->id}");

        $response->assertStatus(403);
    }

    /**
     * Test validación de campos requeridos al crear documento
     */
    public function test_document_creation_requires_fields()
    {
        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => '',
                'category_id' => '',
                'status_id' => '',
                'priority' => '',
            ]);

        $response->assertSessionHasErrors(['title', 'category_id', 'status_id', 'priority']);
    }

    /**
     * Test que el código de documento debe ser único por empresa
     */
    public function test_document_code_must_be_unique_per_company()
    {
        Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'document_number' => 'UNIQUE-001',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Test Document',
                'document_number' => 'UNIQUE-001',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
            ]);

        $response->assertSessionHasErrors('document_number');
    }
}
