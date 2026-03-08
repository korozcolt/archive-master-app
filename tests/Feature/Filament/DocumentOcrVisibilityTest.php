<?php

use App\Filament\Resources\DocumentResource\Pages\ViewDocument;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeDocumentOcrVisibilityContext(): array
{
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    $admin = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);
    $adminRole = Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);
    $admin->assignRole($adminRole);

    $portalUser = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $portalUser->id,
        'assigned_to' => $portalUser->id,
        'title' => 'Documento OCR Visible',
        'content' => 'Contenido OCR visible tanto en portal como en administración.',
        'metadata' => [
            'ocr_processed' => true,
            'ocr_result' => [
                'word_count' => 9,
                'processed_at' => now()->toISOString(),
            ],
        ],
    ]);

    return compact('admin', 'portalUser', 'document');
}

it('shows ocr content in the portal document view', function () {
    $ctx = makeDocumentOcrVisibilityContext();

    $this->actingAs($ctx['portalUser'])
        ->get(route('documents.show', $ctx['document']))
        ->assertOk()
        ->assertSee('Contenido extraído por OCR')
        ->assertSee('Contenido OCR visible tanto en portal como en administración.');
});

it('shows ocr content in the admin document view', function () {
    $ctx = makeDocumentOcrVisibilityContext();

    $this->actingAs($ctx['admin']);

    Livewire::test(ViewDocument::class, [
        'record' => $ctx['document']->id,
    ])
        ->assertSuccessful()
        ->assertSee('Contenido del Documento')
        ->assertSee('Contenido OCR visible tanto en portal como en administración.');
});
