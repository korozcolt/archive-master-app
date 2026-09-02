<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\AguasDeSucreArchiveCentralSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

it('creates archive central department and archive user for aguas de sucre', function () {
    $company = Company::factory()->create([
        'name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
        'legal_name' => ['es' => 'AGUAS DE SUCRE S.A. E.S.P.'],
    ]);

    $branch = Branch::factory()->create([
        'company_id' => $company->id,
        'code' => 'PRINCIPAL',
    ]);

    User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'email' => 'bootstrap@test.local',
    ]);

    SpatieRole::firstOrCreate(['name' => Role::ArchiveManager->value]);

    $this->seed(AguasDeSucreArchiveCentralSeeder::class);

    $department = Department::query()
        ->where('company_id', $company->id)
        ->where('code', 'AC170')
        ->first();

    $user = User::query()
        ->where('email', 'archivo.central.aguasdesucre@test.local')
        ->first();

    expect($department)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and($user->department_id)->toBe($department->id)
        ->and($user->hasRole(Role::ArchiveManager->value))->toBeTrue();
});
