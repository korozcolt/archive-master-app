<?php

use App\Enums\SlaStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds colombian governance defaults for each company', function () {
    $company = Company::factory()->create();

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    expect($company->fresh()->businessCalendars()->where('is_default', true)->exists())->toBeTrue();
    expect($company->fresh()->slaPolicies()->count())->toBeGreaterThanOrEqual(4);
    expect($company->fresh()->documentarySeries()->where('code', 'PQRS')->exists())->toBeTrue();
    expect(data_get($company->fresh()->settings, 'document_governance.jurisdiction'))->toBe('CO');
});

it('calculates legal sla in business days when a pqrs document is created', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    test()->travelTo(now()->startOfWeek()->setTime(9, 0));

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'received_at' => now(),
        'pqrs_type' => 'peticion_general',
    ]);

    $document->refresh();

    expect($document->slaPolicy)->not->toBeNull();
    expect($document->legal_term_days)->toBe(15);
    expect($document->sla_status)->toBe(SlaStatus::Running);
    expect($document->sla_due_date?->toDateString())->toBe(now()->addWeeks(3)->toDateString());
});

it('freezes sla and preserves traceability when a document is archived', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'received_at' => now(),
        'pqrs_type' => 'peticion_general',
    ]);

    $document->archive($user, 'Cierre del trámite y envío a archivo.');
    $document->refresh();

    expect($document->is_archived)->toBeTrue();
    expect($document->sla_status)->toBe(SlaStatus::Frozen);
    expect($document->sla_frozen_at)->not->toBeNull();
    expect($document->closed_at)->not->toBeNull();
    expect($document->slaEvents()->where('event_type', 'sla_frozen')->exists())->toBeTrue();
});

it('builds archival classification and retention defaults from trd tvd catalog', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $series = DocumentarySeries::query()->where('company_id', $company->id)->where('code', 'PQRS')->firstOrFail();
    $subseries = DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'TRAMITE')->firstOrFail();
    $type = DocumentaryType::query()->where('company_id', $company->id)->where('code', 'EXP')->firstOrFail();

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'trd_series_id' => $series->id,
        'trd_subseries_id' => $subseries->id,
        'documentary_type_id' => $type->id,
        'is_archived' => true,
    ]);

    $document->refresh();

    expect($document->archive_classification_code)->toBe('PQRS.TRAMITE.EXP');
    expect($document->archive_phase?->value)->toBe('gestion');
    expect($document->retention_management_years)->toBe(2);
    expect($document->retention_central_years)->toBe(8);
    expect($document->access_level?->value)->toBe('reservado');
});
