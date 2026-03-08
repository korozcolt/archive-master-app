<?php

use App\Enums\Role as RoleEnum;
use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Receipt;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows receipt-linked documents to regular users across portal flows', function () {
    $company = Company::factory()->create();
    $category = Category::factory()->create([
        'company_id' => $company->id,
        'name' => 'QA Operativo',
    ]);
    $status = Status::factory()->create([
        'company_id' => $company->id,
        'name' => 'Pendiente',
    ]);

    $issuer = User::factory()->create([
        'company_id' => $company->id,
        'email' => 'issuer@example.test',
    ]);
    $recipient = User::factory()->create([
        'company_id' => $company->id,
        'email' => 'recipient@example.test',
    ]);

    $regularUserRole = Role::firstOrCreate([
        'name' => RoleEnum::RegularUser->value,
        'guard_name' => 'web',
    ]);

    $issuer->assignRole($regularUserRole);
    $recipient->assignRole($regularUserRole);

    $visibleDocument = Document::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $issuer->id,
        'assigned_to' => $issuer->id,
        'title' => 'Documento visible por recibido',
    ]);

    Receipt::query()->create([
        'document_id' => $visibleDocument->id,
        'company_id' => $company->id,
        'issued_by' => $issuer->id,
        'recipient_user_id' => $recipient->id,
        'receipt_number' => 'REC-TEST-0001',
        'recipient_name' => $recipient->name,
        'recipient_email' => $recipient->email,
        'issued_at' => now(),
    ]);

    $hiddenDocument = Document::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $issuer->id,
        'assigned_to' => $issuer->id,
        'title' => 'Documento no visible',
    ]);

    $this->actingAs($recipient)
        ->get('/portal')
        ->assertSuccessful()
        ->assertSee('Documento visible por recibido')
        ->assertDontSee('Documento no visible');

    $this->actingAs($recipient)
        ->get('/documents')
        ->assertSuccessful()
        ->assertSee('Documento visible por recibido')
        ->assertDontSee('Documento no visible');

    $this->actingAs($recipient)
        ->get('/portal/reports')
        ->assertSuccessful()
        ->assertSee('Documento visible por recibido')
        ->assertDontSee('Documento no visible');

    $this->actingAs($recipient)
        ->get("/documents/{$visibleDocument->id}")
        ->assertSuccessful()
        ->assertSee('Documento visible por recibido');

    $this->actingAs($recipient)
        ->get("/documents/{$hiddenDocument->id}")
        ->assertForbidden();
});

it('allows regular users to preview receipt-linked documents', function () {
    $company = Company::factory()->create();
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);
    $issuer = User::factory()->create(['company_id' => $company->id]);
    $recipient = User::factory()->create(['company_id' => $company->id]);

    $regularUserRole = Role::firstOrCreate([
        'name' => RoleEnum::RegularUser->value,
        'guard_name' => 'web',
    ]);

    $issuer->assignRole($regularUserRole);
    $recipient->assignRole($regularUserRole);

    $disk = config('documents.files.storage_disk', 'local');
    $path = trim((string) config('documents.files.storage_path', 'documents'), '/').'/portal-receipt-preview.pdf';
    Storage::disk($disk)->put($path, 'pdf-smoke');

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $issuer->id,
        'assigned_to' => $issuer->id,
        'file_path' => $path,
    ]);

    Receipt::query()->create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'issued_by' => $issuer->id,
        'recipient_user_id' => $recipient->id,
        'receipt_number' => 'REC-TEST-0002',
        'recipient_name' => $recipient->name,
        'recipient_email' => $recipient->email,
        'issued_at' => now(),
    ]);

    $this->actingAs($recipient)
        ->get("/documents/{$document->id}/preview")
        ->assertSuccessful();
});
