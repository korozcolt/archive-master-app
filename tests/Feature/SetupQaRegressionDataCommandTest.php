<?php

use App\Enums\Role as AppRole;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a stable qa dataset with credentials and business flows', function () {
    $this->artisan('app:setup-qa-regression-data --password=Laboral2026!')
        ->assertSuccessful()
        ->expectsOutputToContain('Dataset QA listo.');

    $superAdmin = User::query()->where('email', 'qa.superadmin@archivemaster.test')->first();
    $admin = User::query()->where('email', 'qa.admin@archivemaster.test')->first();
    $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->first();
    $receptionist = User::query()->where('email', 'qa.reception@archivemaster.test')->first();
    $regularUser = User::query()->where('email', 'qa.user@archivemaster.test')->first();

    expect($superAdmin)->not->toBeNull();
    expect($admin)->not->toBeNull();
    expect($officeManager)->not->toBeNull();
    expect($receptionist)->not->toBeNull();
    expect($regularUser)->not->toBeNull();
    expect($superAdmin->hasRole(AppRole::SuperAdmin->value))->toBeTrue();
    expect($admin->hasRole(AppRole::Admin->value))->toBeTrue();
    expect($officeManager->hasRole(AppRole::OfficeManager->value))->toBeTrue();
    expect($receptionist->hasRole(AppRole::Receptionist->value))->toBeTrue();
    expect($regularUser->hasRole(AppRole::RegularUser->value))->toBeTrue();

    $approvalDocument = Document::query()->where('document_number', 'QA-APR-0001')->first();
    $receiptDocument = Document::query()->where('document_number', 'QA-REC-0001')->first();

    expect($approvalDocument)->not->toBeNull();
    expect($receiptDocument)->not->toBeNull();
    expect(DocumentVersion::query()->where('document_id', $approvalDocument->id)->exists())->toBeTrue();
    expect(DocumentApproval::query()
        ->where('document_id', $approvalDocument->id)
        ->where('approver_id', $officeManager->id)
        ->where('status', 'pending')
        ->exists())->toBeTrue();
    expect(Receipt::query()
        ->where('document_id', $receiptDocument->id)
        ->where('recipient_user_id', $regularUser->id)
        ->exists())->toBeTrue();
});
