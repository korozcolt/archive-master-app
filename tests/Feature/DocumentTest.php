<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentUploadDraft;
use App\Models\DocumentVersion;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use App\Notifications\DocumentDistributedToOfficeNotification;
use App\Notifications\DocumentDistributionTargetUpdatedNotification;
use App\Services\StickerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as SpatieRole;
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

    public function test_documents_index_uses_latest_version_extension_for_icon_when_document_file_path_is_empty()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'file_path' => null,
            'title' => 'Documento con versión PDF',
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'created_by' => $this->user->id,
            'version_number' => 2,
            'file_path' => 'documents/versiones/documento-prueba.pdf',
            'file_name' => 'documento-prueba.pdf',
            'is_current' => true,
            'change_summary' => 'Versión con archivo',
            'metadata' => [],
        ]);

        $response = $this->actingAs($this->user)->get('/documents');

        $response->assertSuccessful()
            ->assertSee('Documento con versión PDF')
            ->assertSee('data-file-ext="pdf"', false);
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

    public function test_portal_does_not_apply_ten_mb_validation_limit_anymore()
    {
        Storage::fake('local');

        $largeFile = UploadedFile::fake()->create('archivo_grande.pdf', 15000, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Documento grande',
                'document_number' => 'FILE-XL-001',
                'description' => 'Archivo superior a 10MB',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
                'file' => $largeFile,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('documents', [
            'document_number' => 'FILE-XL-001',
            'title' => 'Documento grande',
        ]);
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

    public function test_receptionist_can_distribute_document_to_multiple_departments()
    {
        Notification::fake();

        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->user->branch_id,
            'department_id' => $this->user->department_id,
        ]);
        $receptionist->assignRole($receptionRole);

        $departmentOne = Department::factory()->create(['company_id' => $this->company->id]);
        $departmentTwo = Department::factory()->create(['company_id' => $this->company->id]);
        $officeUserOne = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOne->id,
            'is_active' => true,
        ]);
        $officeUserTwo = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentTwo->id,
            'is_active' => true,
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $response = $this->actingAs($receptionist)->post(route('documents.distributions.store', $document), [
            'department_ids' => [$departmentOne->id, $departmentTwo->id],
            'routing_note' => 'Revisar y responder por favor',
        ]);

        $response->assertRedirect(route('documents.show', $document));

        $this->assertDatabaseHas('document_distributions', [
            'document_id' => $document->id,
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('document_distribution_targets', [
            'department_id' => $departmentOne->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('document_distribution_targets', [
            'department_id' => $departmentTwo->id,
            'status' => 'sent',
        ]);

        Notification::assertSentTo($officeUserOne, DocumentDistributedToOfficeNotification::class);
        Notification::assertSentTo($officeUserTwo, DocumentDistributedToOfficeNotification::class);
    }

    public function test_archive_manager_can_view_document_without_location_and_assign_physical_location()
    {
        $archiveRole = SpatieRole::firstOrCreate(['name' => 'archive_manager', 'guard_name' => 'web']);
        $archiveManager = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $archiveManager->assignRole($archiveRole);

        $creator = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $creator->id,
            'assigned_to' => $creator->id,
            'physical_location_id' => null,
        ]);

        $location = PhysicalLocation::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $this->actingAs($archiveManager)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Archivo físico')
            ->assertSee('Imprimir etiqueta');

        $response = $this->actingAs($archiveManager)
            ->post(route('documents.archive-location.update', $document), [
                'physical_location_id' => $location->id,
                'archive_note' => 'Ingreso inicial a archivo central',
            ]);

        $response->assertRedirect(route('documents.show', $document));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'physical_location_id' => $location->id,
        ]);

        $this->assertDatabaseHas('document_location_history', [
            'document_id' => $document->id,
            'physical_location_id' => $location->id,
            'movement_type' => 'stored',
            'moved_by' => $archiveManager->id,
        ]);
    }

    public function test_non_archive_manager_cannot_assign_physical_location_from_portal()
    {
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $receptionist->assignRole($receptionRole);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $location = PhysicalLocation::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $this->actingAs($receptionist)
            ->post(route('documents.archive-location.update', $document), [
                'physical_location_id' => $location->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('document_location_history', [
            'document_id' => $document->id,
            'physical_location_id' => $location->id,
        ]);
    }

    public function test_document_sticker_data_contains_direct_document_url()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $data = app(StickerService::class)->generateForDocument($document);

        expect($data['document_url'])->toBe(route('documents.show', $document));
        expect($data['barcode'])->toBeString();
        expect($data['qrcode'])->toBeString();
    }

    public function test_office_manager_can_view_and_update_distribution_target_for_their_department()
    {
        Notification::fake();

        $officeRole = SpatieRole::firstOrCreate(['name' => 'office_manager', 'guard_name' => 'web']);
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);

        $departmentOffice = Department::factory()->create(['company_id' => $this->company->id]);
        $departmentOther = Department::factory()->create(['company_id' => $this->company->id]);

        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOther->id,
        ]);
        $receptionist->assignRole($receptionRole);

        $officeManager = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOffice->id,
        ]);
        $officeManager->assignRole($officeRole);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $distribution = $document->distributions()->create([
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
            'sent_at' => now(),
        ]);

        $target = $distribution->targets()->create([
            'department_id' => $departmentOffice->id,
            'status' => 'sent',
            'sent_at' => now(),
            'last_activity_at' => now(),
            'last_updated_by' => $receptionist->id,
        ]);

        $listResponse = $this->actingAs($officeManager)->get('/documents');
        $listResponse->assertOk();
        $listResponse->assertSee($document->title);

        $updateResponse = $this->actingAs($officeManager)->post(route('documents.distribution-targets.update', [$document, $target]), [
            'action' => 'respond_comment',
            'note' => 'Revisado por oficina y respuesta emitida.',
        ]);

        $updateResponse->assertRedirect(route('documents.show', $document));

        $this->assertDatabaseHas('document_distribution_targets', [
            'id' => $target->id,
            'status' => 'responded',
            'response_note' => 'Revisado por oficina y respuesta emitida.',
            'last_updated_by' => $officeManager->id,
        ]);

        Notification::assertSentTo(
            $receptionist,
            DocumentDistributionTargetUpdatedNotification::class,
            function (DocumentDistributionTargetUpdatedNotification $notification): bool {
                return $notification->target->status === 'responded'
                    && $notification->target->response_type === 'comment';
            }
        );
    }

    public function test_office_manager_can_reject_distributed_document_and_notify_creator()
    {
        Notification::fake();

        $officeRole = SpatieRole::firstOrCreate(['name' => 'office_manager', 'guard_name' => 'web']);
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);

        $departmentOffice = Department::factory()->create(['company_id' => $this->company->id]);
        $departmentReception = Department::factory()->create(['company_id' => $this->company->id]);

        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentReception->id,
        ]);
        $receptionist->assignRole($receptionRole);

        $officeManager = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOffice->id,
        ]);
        $officeManager->assignRole($officeRole);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $distribution = $document->distributions()->create([
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
            'sent_at' => now(),
        ]);

        $target = $distribution->targets()->create([
            'department_id' => $departmentOffice->id,
            'status' => 'sent',
            'response_type' => 'none',
            'sent_at' => now(),
            'last_activity_at' => now(),
            'last_updated_by' => $receptionist->id,
        ]);

        $response = $this->actingAs($officeManager)->post(route('documents.distribution-targets.update', [$document, $target]), [
            'action' => 'reject',
            'note' => 'No corresponde a esta oficina.',
        ]);

        $response->assertRedirect(route('documents.show', $document));

        $this->assertDatabaseHas('document_distribution_targets', [
            'id' => $target->id,
            'status' => 'rejected',
            'response_type' => 'comment',
            'rejected_reason' => 'No corresponde a esta oficina.',
            'responded_by' => $officeManager->id,
        ]);

        Notification::assertSentTo(
            $receptionist,
            DocumentDistributionTargetUpdatedNotification::class,
            function (DocumentDistributionTargetUpdatedNotification $notification): bool {
                return $notification->target->status === 'rejected'
                    && $notification->target->rejected_reason === 'No corresponde a esta oficina.';
            }
        );
    }

    public function test_office_manager_can_respond_with_official_document_and_notify_creator()
    {
        Notification::fake();

        $officeRole = SpatieRole::firstOrCreate(['name' => 'office_manager', 'guard_name' => 'web']);
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);

        $departmentOffice = Department::factory()->create(['company_id' => $this->company->id]);
        $departmentReception = Department::factory()->create(['company_id' => $this->company->id]);

        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentReception->id,
        ]);
        $receptionist->assignRole($receptionRole);

        $officeManager = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOffice->id,
        ]);
        $officeManager->assignRole($officeRole);

        $incomingDocument = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
            'department_id' => $departmentReception->id,
        ]);

        $responseDocument = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $officeManager->id,
            'assigned_to' => $officeManager->id,
            'department_id' => $departmentOffice->id,
            'title' => 'Respuesta Oficial Jurídica',
        ]);

        $distribution = $incomingDocument->distributions()->create([
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
            'sent_at' => now(),
        ]);

        $target = $distribution->targets()->create([
            'department_id' => $departmentOffice->id,
            'status' => 'in_review',
            'response_type' => 'none',
            'sent_at' => now(),
            'reviewed_at' => now(),
            'last_activity_at' => now(),
            'last_updated_by' => $officeManager->id,
        ]);

        $response = $this->actingAs($officeManager)->post(route('documents.distribution-targets.update', [$incomingDocument, $target]), [
            'action' => 'respond_document',
            'response_document_id' => $responseDocument->id,
            'note' => 'Se adjunta respuesta oficial.',
        ]);

        $response->assertRedirect(route('documents.show', $incomingDocument));

        $this->assertDatabaseHas('document_distribution_targets', [
            'id' => $target->id,
            'status' => 'responded',
            'response_type' => 'outgoing_document',
            'response_document_id' => $responseDocument->id,
            'responded_by' => $officeManager->id,
        ]);

        Notification::assertSentTo(
            $receptionist,
            DocumentDistributionTargetUpdatedNotification::class,
            function (DocumentDistributionTargetUpdatedNotification $notification) use ($responseDocument): bool {
                return $notification->target->status === 'responded'
                    && $notification->target->response_document_id === $responseDocument->id;
            }
        );
    }

    public function test_office_manager_cannot_respond_with_document_from_another_department()
    {
        $officeRole = SpatieRole::firstOrCreate(['name' => 'office_manager', 'guard_name' => 'web']);
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);

        $departmentOffice = Department::factory()->create(['company_id' => $this->company->id]);
        $departmentOther = Department::factory()->create(['company_id' => $this->company->id]);

        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOther->id,
        ]);
        $receptionist->assignRole($receptionRole);

        $officeManager = User::factory()->create([
            'company_id' => $this->company->id,
            'department_id' => $departmentOffice->id,
        ]);
        $officeManager->assignRole($officeRole);

        $incomingDocument = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $foreignResponseDocument = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'department_id' => $departmentOther->id,
        ]);

        $distribution = $incomingDocument->distributions()->create([
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
            'sent_at' => now(),
        ]);

        $target = $distribution->targets()->create([
            'department_id' => $departmentOffice->id,
            'status' => 'sent',
            'response_type' => 'none',
            'sent_at' => now(),
            'last_activity_at' => now(),
            'last_updated_by' => $receptionist->id,
        ]);

        $response = $this->actingAs($officeManager)
            ->from(route('documents.show', $incomingDocument))
            ->post(route('documents.distribution-targets.update', [$incomingDocument, $target]), [
                'action' => 'respond_document',
                'response_document_id' => $foreignResponseDocument->id,
            ]);

        $response->assertRedirect(route('documents.show', $incomingDocument));
        $response->assertSessionHasErrors('response_document_id');

        $this->assertDatabaseHas('document_distribution_targets', [
            'id' => $target->id,
            'status' => 'sent',
            'response_document_id' => null,
        ]);
    }

    public function test_cannot_distribute_document_again_to_an_already_distributed_department()
    {
        $receptionRole = SpatieRole::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        $receptionist = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->user->branch_id,
            'department_id' => $this->user->department_id,
        ]);
        $receptionist->assignRole($receptionRole);

        $department = Department::factory()->create(['company_id' => $this->company->id]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $receptionist->id,
            'assigned_to' => $receptionist->id,
        ]);

        $distribution = $document->distributions()->create([
            'company_id' => $this->company->id,
            'created_by' => $receptionist->id,
            'status' => 'open',
            'sent_at' => now(),
        ]);

        $distribution->targets()->create([
            'department_id' => $department->id,
            'status' => 'sent',
            'sent_at' => now(),
            'last_activity_at' => now(),
            'last_updated_by' => $receptionist->id,
        ]);

        $response = $this->actingAs($receptionist)
            ->from(route('documents.show', $document))
            ->post(route('documents.distributions.store', $document), [
                'department_ids' => [$department->id],
                'routing_note' => 'Intento duplicado',
            ]);

        $response->assertRedirect(route('documents.show', $document));
        $response->assertSessionHasErrors('department_ids');

        $this->assertSame(
            1,
            $document->distributions()->withCount('targets')->get()->sum('targets_count')
        );
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

    public function test_document_preview_route_returns_inline_response_for_previewable_files()
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test-preview.pdf', 'fake-pdf-content');

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'file_path' => 'documents/test-preview.pdf',
        ]);

        $response = $this->actingAs($this->user)->get(route('documents.preview', $document->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
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
        $response->assertDontSee('<label for="file" class="block">', false);
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
