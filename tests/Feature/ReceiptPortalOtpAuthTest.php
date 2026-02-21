<?php

use App\Enums\Role as AppRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\PortalLoginOtp;
use App\Models\Receipt;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createReceptionistUserForReceiptFlow(): User
{
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);

    $receptionist = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    Role::firstOrCreate(['name' => AppRole::Receptionist->value]);
    Role::firstOrCreate(['name' => AppRole::RegularUser->value]);
    $receptionist->assignRole(AppRole::Receptionist->value);

    return $receptionist;
}

it('creates receipt and regular user when receptionist creates a document', function () {
    $receptionist = createReceptionistUserForReceiptFlow();
    expect($receptionist->fresh()->roles()->where('name', AppRole::Receptionist->value)->exists())->toBeTrue();
    $category = Category::factory()->create(['company_id' => $receptionist->company_id]);
    $status = Status::factory()->create(['company_id' => $receptionist->company_id]);

    $this->actingAs($receptionist)
        ->post(route('documents.store'), [
            'title' => 'Documento recibido',
            'description' => 'Registro de entrada',
            'category_id' => $category->id,
            'status_id' => $status->id,
            'priority' => 'medium',
            'recipient_name' => 'Cliente Final',
            'recipient_email' => 'cliente@example.com',
            'recipient_phone' => '3001234567',
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'Documento recibido')->first();

    expect($document)->not->toBeNull();
    expect(Receipt::query()->count())->toBe(1);
    expect(Receipt::query()->first()->recipient_email)->toBe('cliente@example.com');

    $recipient = User::query()->where('email', 'cliente@example.com')->first();

    expect($recipient)->not->toBeNull();
    expect($recipient->hasRole(AppRole::RegularUser->value))->toBeTrue();

    $this->assertDatabaseHas('receipts', [
        'document_id' => $document->id,
        'recipient_email' => 'cliente@example.com',
        'issued_by' => $receptionist->id,
    ]);
});

it('generates otp from receipt data and allows portal login', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);

    Role::firstOrCreate(['name' => AppRole::RegularUser->value]);

    $regularUser = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'email' => 'portal.user@example.com',
    ]);
    $regularUser->assignRole(AppRole::RegularUser->value);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'created_by' => $regularUser->id,
        'assigned_to' => $regularUser->id,
    ]);

    $receipt = Receipt::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'issued_by' => $regularUser->id,
        'recipient_user_id' => $regularUser->id,
        'receipt_number' => 'REC-TEST-0001',
        'recipient_name' => 'Portal User',
        'recipient_email' => $regularUser->email,
        'recipient_phone' => '3000000000',
        'issued_at' => now(),
    ]);

    $requestResponse = $this->post(route('portal.auth.request-otp'), [
        'receipt_number' => $receipt->receipt_number,
        'email' => $regularUser->email,
    ]);

    $requestResponse->assertRedirect(route('portal.auth.verify.form'));

    $otp = PortalLoginOtp::query()->latest()->first();

    expect($otp)->not->toBeNull();
    expect($otp->user_id)->toBe($regularUser->id);

    $otpCode = '123456';
    $otp->update([
        'code_hash' => Hash::make($otpCode),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->post(route('portal.auth.verify'), [
        'receipt_number' => $receipt->receipt_number,
        'email' => $regularUser->email,
        'otp_code' => $otpCode,
    ])->assertRedirect('/portal');

    $this->assertAuthenticatedAs($regularUser);
});

it('allows receipt owner to view and download receipt pdf', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);

    Role::firstOrCreate(['name' => AppRole::RegularUser->value]);

    $regularUser = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);
    $regularUser->assignRole(AppRole::RegularUser->value);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'created_by' => $regularUser->id,
        'assigned_to' => $regularUser->id,
    ]);

    $receipt = Receipt::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'issued_by' => $regularUser->id,
        'recipient_user_id' => $regularUser->id,
        'receipt_number' => 'REC-TEST-0002',
        'recipient_name' => 'Portal User',
        'recipient_email' => $regularUser->email,
        'recipient_phone' => '3000000000',
        'issued_at' => now(),
    ]);

    $this->actingAs($regularUser)
        ->get(route('receipts.show', $receipt))
        ->assertSuccessful()
        ->assertSee($receipt->receipt_number);

    $this->actingAs($regularUser)
        ->get(route('receipts.download', $receipt))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});
