<?php

use App\Enums\ArchivePhase;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

it('loads the portal document index without the expensive total count query', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);
    $role = SpatieRole::firstOrCreate(['name' => Role::ArchiveOperator->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);
    $user->assignRole($role);

    Document::factory()
        ->count(3)
        ->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'assigned_to' => null,
            'archive_phase' => ArchivePhase::Central->value,
            'metadata' => ['entry_mode' => 'historical'],
        ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('Mostrando hasta 15 documento(s) por página');

    expect($queries)->not->toContain(fn (string $sql): bool => str_contains($sql, 'select count(*) as aggregate from `documents`'));
});
