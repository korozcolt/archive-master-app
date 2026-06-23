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
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
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
        'name' => ['es' => 'Secretaria General'],
        'code' => 'SG110',
    ]);

    $archiveRole = SpatieRole::firstOrCreate(['name' => Role::ArchiveManager->value, 'guard_name' => 'web']);
    $operatorRole = SpatieRole::firstOrCreate(['name' => Role::ArchiveOperator->value, 'guard_name' => 'web']);
    $officeRole = SpatieRole::firstOrCreate(['name' => Role::OfficeManager->value, 'guard_name' => 'web']);

    $archiveManager = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $archiveDepartment->id,
        'is_active' => true,
    ]);
    $archiveManager->assignRole($archiveRole);

    $archiveOperator = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $archiveDepartment->id,
        'is_active' => true,
    ]);
    $archiveOperator->assignRole($operatorRole);

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
        'structured_data' => [
            'nivel' => 'Sotano',
            'archivo' => 'Principal',
            'estante' => '01',
            'entrepano' => '01',
            'caja' => '001',
        ],
        'full_path' => 'Nivel Sotano / Archivo Principal / Estante 01 / Entrepano 01 / Caja 001',
        'capacity_total' => 10,
        'capacity_used' => 0,
    ]);

    $series = DocumentarySeries::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'code' => 'G100',
        'name' => 'Contratos',
    ]);
    $subseries = DocumentarySubseries::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'documentary_series_id' => $series->id,
        'code' => 'G100-01',
        'name' => 'Contratos de obra',
    ]);
    $documentaryType = DocumentaryType::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'documentary_subseries_id' => $subseries->id,
        'code' => 'G100-01-01',
        'name' => 'Contrato',
        'access_level_default' => DocumentAccessLevel::Interno,
    ]);

    return compact(
        'company',
        'branch',
        'archiveDepartment',
        'producerDepartment',
        'officeDepartment',
        'archiveManager',
        'archiveOperator',
        'officeManager',
        'category',
        'archivedStatus',
        'location',
        'series',
        'subseries',
        'documentaryType',
    );
}

it('allows archive manager to upload historical documents directly to a central archive box', function () {
    Storage::fake('local');
    Queue::fake();
    $setup = historicalFlowSetup();

    $response = $this->actingAs($setup['archiveManager'])
        ->post(route('documents.historical.store'), [
            'rows' => [
                [
                    'file' => UploadedFile::fake()->create('Acta Junta 1999.pdf', 120, 'application/pdf'),
                    'documentary_type_id' => $setup['documentaryType']->id,
                    'folder' => 'Carpeta 03',
                    'volume' => 'Tomo 1',
                    'reference_code' => 'G100-1999-ACT-01',
                    'year' => 1999,
                    'description' => 'Acta historica de gerencia.',
                ],
            ],
            'category_id' => $setup['category']->id,
            'original_department_id' => $setup['producerDepartment']->id,
            'physical_location_id' => $setup['location']->id,
            'digital_document_type' => 'original',
            'physical_document_type' => 'copia',
            'date_start' => '1999-01-01',
            'date_end' => '1999-12-31',
            'keywords' => 'junta, acta, decisiones',
        ]);

    $response->assertRedirect(route('documents.index', ['archive_phase' => ArchivePhase::Central->value]));

    $document = Document::query()->firstOrFail();

    expect($document->isHistoricalEntry())->toBeTrue()
        ->and($document->archive_phase?->value)->toBe(ArchivePhase::Central->value)
        ->and($document->physical_location_id)->toBe($setup['location']->id)
        ->and($document->trd_series_id)->toBe($setup['series']->id)
        ->and($document->trd_subseries_id)->toBe($setup['subseries']->id)
        ->and($document->documentary_type_id)->toBe($setup['documentaryType']->id)
        ->and($document->department_id)->toBe($setup['archiveDepartment']->id)
        ->and($document->assigned_to)->toBeNull()
        ->and($document->status_id)->toBe($setup['archivedStatus']->id)
        ->and($document->digital_document_type)->toBe('original')
        ->and($document->physical_document_type)->toBe('copia')
        ->and($document->sla_status)->toBe(SlaStatus::Frozen)
        ->and($document->sla_frozen_at)->not->toBeNull()
        ->and(data_get($document->metadata, 'historical.original_department_id'))->toBe($setup['producerDepartment']->id)
        ->and(data_get($document->metadata, 'historical.box'))->toBe('Caja 001')
        ->and(data_get($document->metadata, 'historical.reference_code'))->toBe('G100-1999-ACT-01')
        ->and(data_get($document->metadata, 'historical.workflow'))->toBe('historical_upload')
        ->and(data_get($document->metadata, 'historical.digital_document_type'))->toBe('original')
        ->and(data_get($document->metadata, 'historical.physical_document_type'))->toBe('copia');

    expect($document->locationHistory()->where('movement_type', 'stored')->exists())->toBeTrue()
        ->and($setup['location']->refresh()->capacity_used)->toBe(1)
        ->and($document->slaEvents()->where('event_type', 'sla_frozen')->where('metadata->reason', 'historical_upload')->exists())->toBeTrue();

    Queue::assertNotPushed(ProcessDocumentOcr::class);
});

it('shows a box based historical upload experience for archive users', function () {
    $setup = historicalFlowSetup();

    $this->actingAs($setup['archiveManager'])
        ->get(route('documents.historical.create'))
        ->assertOk()
        ->assertSee('Carga por caja')
        ->assertSee('Estante')
        ->assertSee('Entrepano')
        ->assertSee('Caja')
        ->assertSee('Tipo documental')
        ->assertSee('Agregar fila')
        ->assertSee('Incorporar al archivo central')
        ->assertSee('x-model="selectedShelf"', false)
        ->assertSee('x-model="selectedBay"', false)
        ->assertSee('x-model="selectedLocationId"', false)
        ->assertSee('name="physical_location_id"', false);
});

it('remembers the last physical box used by the archive user in the historical form', function () {
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
        ->assertSee('selectedLocationId:', false)
        ->assertSee((string) $setup['location']->id);
});

it('allows archive operator to upload historical documents into an active box', function () {
    Storage::fake('local');
    Queue::fake();
    $setup = historicalFlowSetup();

    $response = $this->actingAs($setup['archiveOperator'])
        ->post(route('documents.historical.store'), [
            'rows' => [
                [
                    'file' => UploadedFile::fake()->create('Contrato 2024.pdf', 100, 'application/pdf'),
                    'documentary_type_id' => $setup['documentaryType']->id,
                    'folder' => 'Carpeta 07',
                    'volume' => '1 DE 1',
                    'reference_code' => 'G100-2024-001',
                    'year' => 2024,
                ],
            ],
            'category_id' => $setup['category']->id,
            'original_department_id' => $setup['producerDepartment']->id,
            'physical_location_id' => $setup['location']->id,
            'digital_document_type' => 'copia',
            'physical_document_type' => 'original',
        ]);

    $response->assertRedirect(route('documents.index', ['archive_phase' => ArchivePhase::Central->value]));

    $document = Document::query()->firstOrFail();

    expect($document->created_by)->toBe($setup['archiveOperator']->id)
        ->and($document->physical_location_id)->toBe($setup['location']->id)
        ->and($document->locationHistory()->where('movement_type', 'stored')->exists())->toBeTrue()
        ->and($setup['location']->refresh()->capacity_used)->toBe(1);
});

it('prevents archive operator from correcting a document physical location after upload', function () {
    $setup = historicalFlowSetup();
    $document = Document::factory()->create([
        'company_id' => $setup['company']->id,
        'created_by' => $setup['archiveOperator']->id,
        'physical_location_id' => $setup['location']->id,
        'metadata' => ['entry_mode' => 'historical'],
    ]);

    $this->actingAs($setup['archiveOperator'])
        ->post(route('documents.archive-location.update', $document), [
            'physical_location_id' => $setup['location']->id,
        ])
        ->assertForbidden();
});

it('requires a documentary type for box based historical uploads', function () {
    Storage::fake('local');
    $setup = historicalFlowSetup();

    $this->actingAs($setup['archiveOperator'])
        ->post(route('documents.historical.store'), [
            'rows' => [
                [
                    'file' => UploadedFile::fake()->create('Sin tipo.pdf', 100, 'application/pdf'),
                ],
            ],
            'category_id' => $setup['category']->id,
            'original_department_id' => $setup['producerDepartment']->id,
            'physical_location_id' => $setup['location']->id,
            'digital_document_type' => 'copia',
            'physical_document_type' => 'original',
        ])
        ->assertSessionHasErrors('documentary_type_id');
});

it('rejects historical uploads into a full box', function () {
    Storage::fake('local');
    $setup = historicalFlowSetup();
    $setup['location']->forceFill([
        'capacity_total' => 1,
        'capacity_used' => 1,
    ])->save();

    $this->actingAs($setup['archiveOperator'])
        ->post(route('documents.historical.store'), [
            'rows' => [
                [
                    'file' => UploadedFile::fake()->create('Caja llena.pdf', 100, 'application/pdf'),
                    'documentary_type_id' => $setup['documentaryType']->id,
                ],
            ],
            'category_id' => $setup['category']->id,
            'original_department_id' => $setup['producerDepartment']->id,
            'physical_location_id' => $setup['location']->id,
            'digital_document_type' => 'copia',
            'physical_document_type' => 'original',
        ])
        ->assertSessionHasErrors('physical_location_id');
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
        'title' => 'Acta Historica de Gerencia',
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
                'keywords_text' => 'gerencia acta historica',
            ],
        ],
    ]);

    $this->actingAs($setup['officeManager'])
        ->get(route('documents.index', ['search' => 'Gerencia']))
        ->assertOk()
        ->assertSee('Acta Historica de Gerencia')
        ->assertSee('Productora: Gerencia');

    $this->actingAs($setup['officeManager'])
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Carga hist')
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
        'title' => 'Informe Reservado Historico',
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

it('allows the creator of a historical document to view it regardless of role or access level', function () {
    $setup = historicalFlowSetup();

    $regularUserRole = SpatieRole::firstOrCreate(['name' => Role::RegularUser->value, 'guard_name' => 'web']);
    $creatorUser = User::factory()->create([
        'company_id' => $setup['company']->id,
        'branch_id' => $setup['branch']->id,
        'department_id' => $setup['officeDepartment']->id,
        'is_active' => true,
    ]);
    $creatorUser->assignRole($regularUserRole);

    $document = Document::factory()->create([
        'company_id' => $setup['company']->id,
        'branch_id' => $setup['branch']->id,
        'department_id' => null,
        'category_id' => $setup['category']->id,
        'status_id' => $setup['archivedStatus']->id,
        'created_by' => $creatorUser->id,
        'assigned_to' => null,
        'title' => 'Documento Creado Por Mi',
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

    $this->actingAs($creatorUser)
        ->get(route('documents.show', $document))
        ->assertOk();
});
