<?php

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows file extension icon in recent documents', function (): void {
    $role = Role::create(['name' => RoleEnum::Receptionist->value]);
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $status = Status::factory()->create(['company_id' => $company->id]);
    $category = Category::factory()->create(['company_id' => $company->id]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);
    $user->assignRole($role);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Contrato PDF',
        'document_number' => 'DOC-ICON-001',
        'file_path' => 'documents/test/contrato-final.pdf',
    ]);

    $this->actingAs($user)
        ->get('/portal')
        ->assertSuccessful()
        ->assertSee('Contrato PDF')
        ->assertSee('data-file-ext="pdf"', false);
});
