<?php

use App\Enums\Role as RoleEnum;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows sla and archive operational trays in the portal dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);
    $status = Status::factory()->create([
        'company_id' => $company->id,
    ]);

    $role = Role::firstOrCreate([
        'name' => RoleEnum::OfficeManager->value,
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Documento en advertencia',
        'sla_status' => 'warning',
        'sla_due_date' => now()->addDay(),
    ]);

    Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Documento vencido',
        'sla_status' => 'overdue',
        'sla_due_date' => now()->subDay(),
    ]);

    Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Documento listo para archivar',
        'closed_at' => now(),
    ]);

    Document::factory()->create([
        'company_id' => $company->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Documento archivado sin TRD',
        'is_archived' => true,
        'archived_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/portal')
        ->assertSuccessful()
        ->assertSee('Atención SLA')
        ->assertSee('Consulta prioritaria')
        ->assertSee('Bloqueantes')
        ->assertSee('Listos para custodiar')
        ->assertSee('Archivo incompleto')
        ->assertSee('Documento en advertencia')
        ->assertSee('Documento vencido');
});

it('shows archive focused actions for archive managers on the portal dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $role = Role::firstOrCreate([
        'name' => RoleEnum::ArchiveManager->value,
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal')
        ->assertSuccessful()
        ->assertSee('Archivo Central')
        ->assertSee('Control de archivo histórico')
        ->assertSee('Carga histórica')
        ->assertSee('Ver archivo central')
        ->assertDontSee('Nuevo Documento');
});

it('loads the archive portal dashboard without the expensive portal visibility count', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $role = Role::firstOrCreate([
        'name' => RoleEnum::ArchiveOperator->value,
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    Document::factory()
        ->count(3)
        ->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'assigned_to' => null,
            'archive_phase' => \App\Enums\ArchivePhase::Central->value,
            'metadata' => ['entry_mode' => 'historical'],
        ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)
        ->get('/portal')
        ->assertSuccessful()
        ->assertSee('Archivo Central');

    expect(collect($queries)->contains(
        fn (string $sql): bool => str_contains($sql, 'select count(*) as aggregate from `documents`')
            && str_contains($sql, 'json_extract')
            && str_contains($sql, 'entry_mode')
    ))->toBeFalse();
});
