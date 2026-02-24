<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

test('receptionist can access portal login with email and password', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);

    SpatieRole::firstOrCreate(['name' => Role::Receptionist->value, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'email' => 'reception@test.local',
        'password' => bcrypt('Secret123!'),
        'is_active' => true,
    ]);
    $user->assignRole(Role::Receptionist->value);

    $response = $this->post('/login', [
        'email' => 'reception@test.local',
        'password' => 'Secret123!',
    ]);

    $response->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);
});

test('invalid portal password login returns validation error', function () {
    $response = $this->from('/login')->post('/login', [
        'email' => 'invalid@example.com',
        'password' => 'wrong-pass',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('password_login');
});
