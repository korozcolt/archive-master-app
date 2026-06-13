<?php

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\Role;
use App\Enums\SlaStatus;
use App\Jobs\ProcessDocumentOcr;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function historicalFlowSetup(): array
{
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $archiveDepartment = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => 'Archivo Central'],
        'code' => 'AC170',
    ]);
    $producerDepartment = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => 'Gerencia'],
        'code' => 'G100',
    ]);
    $officeDepartment = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => 'Secretaría General'],
        'code' => 'SG110',
    ]);

    $archiveRole = SpatieRole::firstOrCreate(['name' => Role::ArchiveManager->value]);
    $officeRole = SpatieRole::firstOrCreate(['name' => Role::OfficeManager->value]);

    $archiveManager = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $archiveDepartment->id,
        'is_active' => true,
    ]);
    $archiveManager->assignRole($archiveRole);

    $officeManager = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $officeDepartment->id,
        'is_active' => true,
    ]);
    $officeManager->assignRole($officeRole);

    $category = Category::factory()->create(['company_id' => $company->id]);
    $archivedStatus = Status::factory()->create([
        'company_id' => $company->id,
        'slug' => 'archivado',
        'name' => 'Archivado',
    ]);
    $location = PhysicalLocation::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
        'full_path' => 'Nivel Sótano / Archivo Principal / Estante 01 / Entrepaño 01',
    ]);

    return compact(
        'company',
        'branch',
        'archiveDepartment',
        'producerDepartment',
        'officeDepartment',
        'archiveManager',
        'officeManager',
        'category',
        'archivedStatus',
        'location',
    );
}

it('allows archive manager to upload historical documents directly to central archive', function () {
    Storage::fake('local');
    Queue::fake();
    $setup = historicalFlowSetup();

    $response = $this->actingAs($setup['archiveManager'])
        ->post(route('documents.historical.store'), [
            'files' => [
                UploadedFile::fake()->create('Acta Junta 1999.pdf', 120, 'application/pdf'),
            ],
            'category_id' => $setup['category']->id,
            'original_department_id' => $setup['producerDepartment']->id,
            'physical_location_id' => $setup['location']->id,
            'description' => 'Digitalización masiva de archivo histórico.',
            'access_level' => DocumentAccessLevel::Interno->value,
            'digital_document_type' => 'original',
            'physical_document_type' => 'copia',
            'date_start' => '1999-01-01',
            'date_end' => '1999-12-31',
            'box' => 'Caja 12',
            'folder' => 'Carpeta 03',
            'volume' => 'Tomo 1',
            'reference_code' => 'G100-1999-ACT-01',
            'keywords' => 'junta, acta, decisiones',
        ]);

    $response->assertRedirect(route('documents.index', ['archive_phase' => ArchivePhase::Central->value]));

    $document = Document::query()->firstOrFail();

    expect($document->isHistoricalEntry())->toBeTrue()
        ->and($document->archive_phase?->value)->toBe(ArchivePhase::Central->value)
        ->and($document->physical_location_id)->toBe($setup['location']->id)
        ->and($document->department_id)->toBe($setup['archiveDepartment']->id)
        ->and($document->assigned_to)->toBeNull()
        ->and($document->status_id)->toBe($setup['archivedStatus']->id)
        ->and($document->digital_document_type)->toBe('original')
        ->and($document->physical_document_type)->toBe('copia')
        ->and($document->sla_status)->toBe(SlaStatus::Frozen)
        ->and($document->sla_frozen_at)->not->toBeNull()
        ->and(data_get($document->metadata, 'historical.original_department_id'))->toBe($setup['producerDepartment']->id)
        ->and(data_get($document->metadata, 'historical.reference_code'))->toBe('G100-1999-ACT-01')
        ->and(data_get($document->metadata, 'historical.workflow'))->toBe('historical_upload')
        ->and(data_get($document->metadata, 'historical.digital_document_type'))->toBe('original')
        ->and(data_get($document->metadata, 'historical.physical_document_type'))->toBe('copia');

    expect($document->slaEvents()->where('event_type', 'sla_frozen')->where('metadata->reason', 'historical_upload')->exists())
        ->toBeTrue();

    Queue::assertNotPushed(ProcessDocumentOcr::class);
});

it('shows a production-ready historical upload experience for archive users', function () {
    $setup = historicalFlowSetup();

    $this->actingAs($setup['archiveManager'])
        ->get(route('documents.historical.create'))
        ->assertOk()
        ->assertSee('Flujo exclusivo de histórico')
        ->assertSee('Cada archivo crea un documento histórico independiente')
        ->assertSee('Buscar ubicación física')
        ->assertSee('Resumen de incorporación')
        ->assertSee('Incorporar al archivo central')
        ->assertSee('Tipo de documento digital')
        ->assertSee('Tipo de documento físico')
        ->assertSee('Cambiar selección')
        ->assertSee('Limpiar')
        ->assertSee('sm:flex-row sm:items-center sm:justify-between', false)
        ->assertSee('name="physical_location_id"', false);
});

it('remembers the last physical location used by the archive manager in the historical form', function () {
    $setup = historicalFlowSetup();

    $setup['archiveManager']->forceFill([
        'settings' => [
            'historical_upload' => [
                'last_physical_location_id' => $setup['location']->id,
            ],
        ],
    ])->saveQuietly();

    $this->actingAs($setup['archiveManager'])
        ->get(route('documents.historical.create'))
        ->assertOk()
        ->assertSee('Se propone automáticamente la última ubicación usada en tu carga histórica anterior.')
        ->assertSee('selectedLocationId: '.$setup['location']->id, false);
});

it('shows internal historical documents to office managers across the company and lets them search by producer', function () {
    $setup = historicalFlowSetup();

    $document = Document::factory()->create([
        'company_id' => $setup['company']->id,
        'branch_id' => $setup['branch']->id,
        'department_id' => null,
        'category_id' => $setup['category']->id,
        'status_id' => $setup['archivedStatus']->id,
        'created_by' => $setup['archiveManager']->id,
        'assigned_to' => null,
        'title' => 'Acta Histórica de Gerencia',
        'archive_phase' => ArchivePhase::Central,
        'access_level' => DocumentAccessLevel::Interno,
        'is_archived' => true,
        'physical_location_id' => $setup['location']->id,
        'metadata' => [
            'entry_mode' => 'historical',
            'historical' => [
                'original_department_id' => $setup['producerDepartment']->id,
                'original_department_name' => 'Gerencia',
                'reference_code' => 'G100-HIS-001',
                'keywords_text' => 'gerencia acta histórica',
            ],
        ],
    ]);

    $this->actingAs($setup['officeManager'])
        ->get(route('documents.index', ['search' => 'Gerencia']))
        ->assertOk()
        ->assertSee('Acta Histórica de Gerencia')
        ->assertSee('Productora: Gerencia');

    $this->actingAs($setup['officeManager'])
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Carga histórica')
        ->assertSee('Archivo central');
});

it('does not expose reserved historical documents to regular office users', function () {
    $setup = historicalFlowSetup();

    $document = Document::factory()->create([
        'company_id' => $setup['company']->id,
        'branch_id' => $setup['branch']->id,
        'department_id' => null,
        'category_id' => $setup['category']->id,
        'status_id' => $setup['archivedStatus']->id,
        'created_by' => $setup['archiveManager']->id,
        'assigned_to' => null,
        'title' => 'Informe Reservado Histórico',
        'archive_phase' => ArchivePhase::Central,
        'access_level' => DocumentAccessLevel::Reservado,
        'is_archived' => true,
        'physical_location_id' => $setup['location']->id,
        'metadata' => [
            'entry_mode' => 'historical',
            'historical' => [
                'original_department_id' => $setup['producerDepartment']->id,
                'original_department_name' => 'Gerencia',
            ],
        ],
    ]);

    $this->actingAs($setup['officeManager'])
        ->get(route('documents.show', $document))
        ->assertForbidden();

    expect(Document::query()
        ->visibleToPortalUser($setup['officeManager'])
        ->whereKey($document->id)
        ->exists())->toBeFalse();
});
