<?php

use App\Enums\Role as AppRole;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function seedAiPermissionsByRole(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (AppRole::cases() as $roleEnum) {
        $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
        $permissions = $roleEnum->getPermissions();

        if ($permissions === ['*']) {
            continue;
        }

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function makeUserWithRole(string $role, int $companyId): User
{
    $user = User::factory()->create(['company_id' => $companyId]);
    $user->assignRole($role);

    return $user;
}

it('allows admin to manage company ai settings in same company', function () {
    seedAiPermissionsByRole();

    $company = Company::factory()->create();
    $admin = makeUserWithRole(AppRole::Admin->value, $company->id);
    $setting = CompanyAiSetting::factory()->create(['company_id' => $company->id]);

    expect($admin->can('view', $setting))->toBeTrue();
    expect($admin->can('update', $setting))->toBeTrue();
});

it('denies admin managing ai settings from another company', function () {
    seedAiPermissionsByRole();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $admin = makeUserWithRole(AppRole::Admin->value, $companyA->id);
    $setting = CompanyAiSetting::factory()->create(['company_id' => $companyB->id]);

    expect($admin->can('view', $setting))->toBeFalse();
});

it('allows receptionist to create ai runs but not manage settings', function () {
    seedAiPermissionsByRole();

    $company = Company::factory()->create();
    $receptionist = makeUserWithRole(AppRole::Receptionist->value, $company->id);

    expect($receptionist->can('create', DocumentAiRun::class))->toBeTrue();
    expect($receptionist->can('viewAny', CompanyAiSetting::class))->toBeFalse();
});

it('allows output view and apply suggestions only with document access and permissions', function () {
    seedAiPermissionsByRole();

    $company = Company::factory()->create();
    $officeManager = makeUserWithRole(AppRole::OfficeManager->value, $company->id);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'department_id' => $officeManager->department_id,
        'created_by' => $officeManager->id,
        'assigned_to' => $officeManager->id,
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'created_by' => $officeManager->id,
    ]);

    $run = DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'document_id' => $document->id,
        'document_version_id' => $version->id,
    ]);

    $output = DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
    ]);

    expect($officeManager->can('view', $output))->toBeTrue();
    expect($officeManager->can('applySuggestions', $output))->toBeTrue();
});
