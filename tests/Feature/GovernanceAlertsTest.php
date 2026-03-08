<?php

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\FinalDisposition;
use App\Enums\Role as AppRole;
use App\Enums\SlaStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Notifications\DocumentArchiveClassificationMissing;
use App\Notifications\DocumentDueSoon;
use App\Notifications\DocumentOverdue;
use App\Notifications\DocumentReadyForArchive;
use App\Services\GovernanceAlertService;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createGovernanceRole(string $role): Role
{
    return Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]);
}

function createGovernanceUser(Company $company, string $role): User
{
    $user = User::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    $user->assignRole(createGovernanceRole($role));

    return $user;
}

function createGovernanceDocument(
    Company $company,
    Status $status,
    User $creator,
    User $assignee,
    array $attributes = [],
): Document {
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $category = Category::factory()->create(['company_id' => $company->id]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $creator->id,
        'assigned_to' => $assignee->id,
    ]);

    if ($attributes !== []) {
        $document->forceFill($attributes)->saveQuietly();
        $document->refresh();
    }

    return $document;
}

it('sends configurable governance alerts for due soon overdue and archive cases', function () {
    Notification::fake();

    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create([
        'settings' => [
            'document_governance' => [
                'warning_days' => [3, 1],
                'escalation_days' => 1,
                'send_due_soon_alerts' => true,
                'send_overdue_alerts' => true,
                'send_archive_ready_alerts' => true,
                'send_archive_incomplete_alerts' => true,
                'notify_supervisors_on_overdue' => true,
            ],
        ],
    ]);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);

    $creator = createGovernanceUser($company, AppRole::Receptionist->value);
    $assignee = createGovernanceUser($company, AppRole::OfficeManager->value);
    $archiveManager = createGovernanceUser($company, AppRole::ArchiveManager->value);
    $admin = createGovernanceUser($company, AppRole::Admin->value);

    $warningDocument = createGovernanceDocument($company, $status, $creator, $assignee, [
        'pqrs_type' => 'peticion_general',
        'sla_status' => SlaStatus::Warning,
        'sla_due_date' => now()->addDay(),
    ]);

    $overdueDocument = createGovernanceDocument($company, $status, $creator, $assignee, [
        'pqrs_type' => 'peticion_general',
        'sla_status' => SlaStatus::Overdue,
        'sla_due_date' => now()->subDays(2),
    ]);

    $readyForArchiveDocument = createGovernanceDocument($company, $status, $creator, $assignee, [
        'closed_at' => now()->subHour(),
        'completed_at' => now()->subHour(),
        'is_archived' => false,
        'sla_status' => SlaStatus::Closed,
    ]);

    $archiveIncompleteDocument = createGovernanceDocument($company, $status, $creator, $assignee, [
        'is_archived' => true,
        'archived_at' => now()->subHour(),
        'archive_phase' => ArchivePhase::Gestion,
        'final_disposition' => FinalDisposition::ConservacionTotal,
        'retention_management_years' => 2,
        'retention_central_years' => 8,
        'trd_series_id' => null,
        'trd_subseries_id' => null,
        'documentary_type_id' => null,
        'access_level' => null,
    ]);

    $summary = app(GovernanceAlertService::class)->processCompany($company);

    expect($summary['due_soon'])->toBeGreaterThanOrEqual(1)
        ->and($summary['overdue'])->toBeGreaterThanOrEqual(1)
        ->and($summary['ready_for_archive'])->toBeGreaterThanOrEqual(2)
        ->and($summary['archive_incomplete'])->toBeGreaterThanOrEqual(2);

    Notification::assertSentTo($assignee, DocumentDueSoon::class, function (DocumentDueSoon $notification) use ($warningDocument) {
        $payload = $notification->toArray($warningDocument->assignee);

        return $payload['document_id'] === $warningDocument->id;
    });

    Notification::assertSentTo($admin, DocumentOverdue::class);
    Notification::assertSentTo($archiveManager, DocumentReadyForArchive::class);
    Notification::assertSentTo($archiveManager, DocumentArchiveClassificationMissing::class);

    expect($overdueDocument->fresh()->escalated_at)->not->toBeNull();
    expect($readyForArchiveDocument->fresh()->is_archived)->toBeFalse();
    expect($archiveIncompleteDocument->fresh()->access_level)->toBeNull();
});

it('honors company alert toggles when governance alerts are disabled', function () {
    Notification::fake();

    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create([
        'settings' => [
            'document_governance' => [
                'send_due_soon_alerts' => false,
                'send_overdue_alerts' => false,
                'send_archive_ready_alerts' => false,
                'send_archive_incomplete_alerts' => false,
                'notify_supervisors_on_overdue' => false,
            ],
        ],
    ]);

    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);

    $creator = createGovernanceUser($company, AppRole::Receptionist->value);
    $assignee = createGovernanceUser($company, AppRole::OfficeManager->value);

    createGovernanceDocument($company, $status, $creator, $assignee, [
        'pqrs_type' => 'peticion_general',
        'sla_status' => SlaStatus::Overdue,
        'sla_due_date' => now()->subDays(2),
        'is_archived' => true,
        'archived_at' => now()->subDay(),
        'archive_phase' => ArchivePhase::Gestion,
        'access_level' => DocumentAccessLevel::Reservado,
    ]);

    $summary = app(GovernanceAlertService::class)->processCompany($company);

    expect($summary)->toBe([
        'due_soon' => 0,
        'overdue' => 0,
        'ready_for_archive' => 0,
        'archive_incomplete' => 0,
    ]);

    Notification::assertNothingSent();
});
