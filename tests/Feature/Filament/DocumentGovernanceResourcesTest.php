<?php

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\FinalDisposition;
use App\Filament\Resources\BusinessCalendarResource;
use App\Filament\Resources\DocumentarySeriesResource;
use App\Filament\Resources\DocumentarySubseriesResource;
use App\Filament\Resources\DocumentaryTypeResource;
use App\Filament\Resources\RetentionScheduleResource;
use App\Filament\Resources\SlaPolicyResource;
use App\Models\BusinessCalendar;
use App\Models\Company;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use App\Models\SlaPolicy;
use App\Models\User;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $this->admin->assignRole($role);

    $this->actingAs($this->admin);
    $this->seed(ColombiaDocumentGovernanceSeeder::class);
});

it('renders governance resource list pages', function () {
    Livewire::test(SlaPolicyResource\Pages\ListSlaPolicies::class)->assertSuccessful();
    Livewire::test(BusinessCalendarResource\Pages\ListBusinessCalendars::class)->assertSuccessful();
    Livewire::test(DocumentarySeriesResource\Pages\ListDocumentarySeries::class)->assertSuccessful();
    Livewire::test(DocumentarySubseriesResource\Pages\ListDocumentarySubseries::class)->assertSuccessful();
    Livewire::test(DocumentaryTypeResource\Pages\ListDocumentaryTypes::class)->assertSuccessful();
    Livewire::test(RetentionScheduleResource\Pages\ListRetentionSchedules::class)->assertSuccessful();
});

it('creates a custom sla policy from filament', function () {
    $calendar = BusinessCalendar::query()->where('company_id', $this->company->id)->firstOrFail();

    Livewire::test(SlaPolicyResource\Pages\CreateSlaPolicy::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'business_calendar_id' => $calendar->id,
            'code' => 'denuncia_prioritaria',
            'name' => 'Denuncia prioritaria',
            'legal_basis' => 'Ley 1755 de 2015',
            'response_term_days' => 7,
            'warning_days' => ['3', '1'],
            'escalation_days' => 1,
            'remission_deadline_days' => 2,
            'requires_subsanation' => true,
            'allows_extension' => false,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SlaPolicy::query()->where('code', 'denuncia_prioritaria')->exists())->toBeTrue();
});

it('creates a business calendar with exception days', function () {
    Livewire::test(BusinessCalendarResource\Pages\CreateBusinessCalendar::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'name' => 'Calendario Operativo 2026',
            'country_code' => 'CO',
            'timezone' => 'America/Bogota',
            'weekend_days' => ['0', '6'],
            'is_default' => false,
            'days' => [
                [
                    'date' => '2026-03-19',
                    'is_business_day' => false,
                    'note' => 'San José',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $calendar = BusinessCalendar::query()->where('name', 'Calendario Operativo 2026')->first();

    expect($calendar)->not->toBeNull()
        ->and($calendar->days()->count())->toBe(1);
});

it('creates documentary catalog resources and retention schedule', function () {
    Livewire::test(DocumentarySeriesResource\Pages\CreateDocumentarySeries::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'code' => 'HC',
            'name' => 'Historias clínicas',
            'description' => 'Serie para gestión clínica',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $series = DocumentarySeries::query()->where('code', 'HC')->firstOrFail();

    Livewire::test(DocumentarySubseriesResource\Pages\CreateDocumentarySubseries::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'documentary_series_id' => $series->id,
            'code' => 'HC-VAL',
            'name' => 'Valoraciones iniciales',
            'description' => 'Subserie para aperturas de caso',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subseries = DocumentarySubseries::query()->where('code', 'HC-VAL')->firstOrFail();

    Livewire::test(DocumentaryTypeResource\Pages\CreateDocumentaryType::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'documentary_subseries_id' => $subseries->id,
            'code' => 'FMT-INI',
            'name' => 'Formato inicial',
            'description' => 'Documento de apertura',
            'access_level_default' => DocumentAccessLevel::Reservado->value,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $type = DocumentaryType::query()->where('code', 'FMT-INI')->firstOrFail();

    Livewire::test(RetentionScheduleResource\Pages\CreateRetentionSchedule::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'documentary_subseries_id' => $subseries->id,
            'documentary_type_id' => $type->id,
            'archive_phase' => ArchivePhase::Gestion->value,
            'management_years' => 5,
            'central_years' => 15,
            'historical_action' => 'Conservación permanente',
            'final_disposition' => FinalDisposition::ConservacionTotal->value,
            'legal_basis' => 'TRD salud vigente',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RetentionSchedule::query()->where('documentary_type_id', $type->id)->exists())->toBeTrue();
});
