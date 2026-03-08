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
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\Status;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createGovernanceReportRole(string $role): Role
{
    return Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]);
}

function createGovernanceReportUser(Company $company, string $role): User
{
    $user = User::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    $user->assignRole(createGovernanceReportRole($role));

    return $user;
}

function createGovernanceReportDocument(Company $company, Status $status, User $creator, User $assignee, array $attributes = []): Document
{
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

it('builds the legal sla governance report with decorated compliance data', function () {
    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create();
    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);

    $admin = createGovernanceReportUser($company, AppRole::Admin->value);
    $assignee = createGovernanceReportUser($company, AppRole::OfficeManager->value);

    $this->actingAs($admin);

    $overdue = createGovernanceReportDocument($company, $status, $admin, $assignee, [
        'pqrs_type' => 'peticion_general',
        'legal_basis' => 'Ley 1755 de 2015',
        'sla_status' => SlaStatus::Overdue,
        'sla_due_date' => now()->subDay(),
    ]);

    createGovernanceReportDocument($company, $status, $admin, $assignee, [
        'pqrs_type' => 'consulta',
        'legal_basis' => 'Ley 1755 de 2015',
        'sla_status' => SlaStatus::Warning,
        'sla_due_date' => now()->addDay(),
    ]);

    $report = app(ReportService::class)->legalSlaGovernanceReport();

    expect($report)->toHaveCount(2)
        ->and($report->firstWhere('id', $overdue->id)?->sla_status_label)->toBe('Vencido')
        ->and($report->firstWhere('id', $overdue->id)?->due_date)->not->toBeNull()
        ->and($report->firstWhere('id', $overdue->id)?->assignee_name)->toBe($assignee->name);
});

it('builds the archive governance report and scopes by authenticated company', function () {
    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);
    $otherStatus = Status::factory()->create([
        'company_id' => $otherCompany->id,
        'is_final' => false,
    ]);

    $admin = createGovernanceReportUser($company, AppRole::Admin->value);
    $assignee = createGovernanceReportUser($company, AppRole::ArchiveManager->value);
    $otherAdmin = createGovernanceReportUser($otherCompany, AppRole::Admin->value);

    $series = DocumentarySeries::query()->where('company_id', $company->id)->where('code', 'PQRS')->firstOrFail();
    $subseries = DocumentarySubseries::query()->where('company_id', $company->id)->where('code', 'TRAMITE')->firstOrFail();
    $type = DocumentaryType::query()->where('company_id', $company->id)->where('code', 'EXP')->firstOrFail();

    $this->actingAs($admin);

    $companyDocument = createGovernanceReportDocument($company, $status, $admin, $assignee, [
        'is_archived' => true,
        'archived_at' => now()->subDay(),
        'archive_phase' => ArchivePhase::Gestion,
        'archive_classification_code' => 'PQRS.TRAMITE.EXP',
        'trd_series_id' => $series->id,
        'trd_subseries_id' => $subseries->id,
        'documentary_type_id' => $type->id,
        'access_level' => DocumentAccessLevel::Reservado,
        'retention_management_years' => 2,
        'retention_central_years' => 8,
        'final_disposition' => FinalDisposition::ConservacionTotal,
    ]);

    createGovernanceReportDocument($otherCompany, $otherStatus, $otherAdmin, $otherAdmin, [
        'is_archived' => true,
        'archived_at' => now()->subDay(),
        'archive_phase' => ArchivePhase::Central,
        'archive_classification_code' => 'OTHER.SEC.SUB',
        'access_level' => DocumentAccessLevel::Interno,
        'retention_management_years' => 1,
        'retention_central_years' => 2,
        'final_disposition' => FinalDisposition::Seleccion,
    ]);

    $report = app(ReportService::class)->archiveGovernanceReport();

    expect($report)->toHaveCount(1)
        ->and($report->sole()->id)->toBe($companyDocument->id)
        ->and($report->sole()->archive_classification_complete)->toBeTrue()
        ->and($report->sole()->archive_phase?->value)->toBe('gestion');
});
