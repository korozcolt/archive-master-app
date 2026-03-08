<?php

use App\Enums\Role as AppRole;
use App\Enums\SlaStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Notifications\DocumentDueSoon;
use App\Notifications\DocumentOverdue;
use Database\Seeders\ColombiaDocumentGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createAlertCommandRole(string $role): Role
{
    return Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]);
}

function createAlertCommandUser(Company $company, string $role): User
{
    $user = User::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    $user->assignRole(createAlertCommandRole($role));

    return $user;
}

function createAlertCommandDocument(Company $company, Status $status, User $creator, User $assignee, array $attributes = []): Document
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

it('processes due and archive governance alerts from the artisan command', function () {
    Notification::fake();

    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create(['active' => true]);
    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);

    $creator = createAlertCommandUser($company, AppRole::Receptionist->value);
    $assignee = createAlertCommandUser($company, AppRole::OfficeManager->value);
    $admin = createAlertCommandUser($company, AppRole::Admin->value);
    $archiveManager = createAlertCommandUser($company, AppRole::ArchiveManager->value);

    createAlertCommandDocument($company, $status, $creator, $assignee, [
        'pqrs_type' => 'peticion_general',
        'sla_status' => SlaStatus::Warning,
        'sla_due_date' => now()->addDay(),
    ]);

    createAlertCommandDocument($company, $status, $creator, $assignee, [
        'closed_at' => now()->subHour(),
        'completed_at' => now()->subHour(),
        'is_archived' => false,
        'sla_status' => SlaStatus::Closed,
    ]);

    $this->artisan('documents:check-due')
        ->expectsOutputToContain('Proceso de alertas por vencer y archivo completado.')
        ->assertSuccessful();

    Notification::assertSentTo($assignee, DocumentDueSoon::class);
    Notification::assertSentTo($archiveManager, \App\Notifications\DocumentReadyForArchive::class);
});

it('processes overdue governance alerts from the artisan command', function () {
    Notification::fake();

    test()->travelTo(now()->startOfWeek()->addDay()->setTime(9, 0));

    $company = Company::factory()->create(['active' => true]);
    $this->seed(ColombiaDocumentGovernanceSeeder::class);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'is_final' => false,
    ]);

    $creator = createAlertCommandUser($company, AppRole::Receptionist->value);
    $assignee = createAlertCommandUser($company, AppRole::OfficeManager->value);
    $admin = createAlertCommandUser($company, AppRole::Admin->value);

    createAlertCommandDocument($company, $status, $creator, $assignee, [
        'pqrs_type' => 'peticion_general',
        'sla_status' => SlaStatus::Overdue,
        'sla_due_date' => now()->subDays(2),
    ]);

    $this->artisan('documents:notify-overdue')
        ->expectsOutputToContain('Proceso de alertas por vencimiento completado.')
        ->assertSuccessful();

    Notification::assertSentTo($admin, DocumentOverdue::class);
});
