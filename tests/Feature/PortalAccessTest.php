<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows portal roles to access portal dashboard', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal')
        ->assertSuccessful();
})->with([
    RoleEnum::OfficeManager->value,
    RoleEnum::ArchiveManager->value,
    RoleEnum::Receptionist->value,
    RoleEnum::RegularUser->value,
]);

it('allows portal roles to access portal reports', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal/reports')
        ->assertSuccessful();
})->with([
    RoleEnum::OfficeManager->value,
    RoleEnum::ArchiveManager->value,
    RoleEnum::Receptionist->value,
    RoleEnum::RegularUser->value,
]);

it('redirects portal roles away from admin', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect('/portal');
})->with([
    RoleEnum::OfficeManager->value,
    RoleEnum::ArchiveManager->value,
    RoleEnum::Receptionist->value,
    RoleEnum::RegularUser->value,
]);

it('redirects admin roles away from portal', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal')
        ->assertRedirect('/admin');
})->with([
    RoleEnum::SuperAdmin->value,
    RoleEnum::Admin->value,
    RoleEnum::BranchAdmin->value,
]);
