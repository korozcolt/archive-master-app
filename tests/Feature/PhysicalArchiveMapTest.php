<?php

use App\Enums\Role as RoleEnum;
use App\Livewire\Portal\PhysicalArchiveMap;
use App\Models\PhysicalLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('blocks guests from accessing physical archive map', function () {
    $this->get('/portal/archive-map')
        ->assertRedirect('/login');
});

it('blocks regular users from accessing physical archive map', function () {
    $role = Role::create(['name' => RoleEnum::RegularUser->value]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal/archive-map')
        ->assertStatus(403);
});

it('allows archive managers and operators to access physical archive map', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal/archive-map')
        ->assertSuccessful()
        ->assertSeeLivewire(PhysicalArchiveMap::class);
})->with([
    RoleEnum::ArchiveManager->value,
    RoleEnum::ArchiveOperator->value,
]);

it('redirects admins from portal archive map to admin panel', function (string $roleName) {
    $role = Role::create(['name' => $roleName]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/portal/archive-map')
        ->assertRedirect('/admin');
})->with([
    RoleEnum::Admin->value,
    RoleEnum::SuperAdmin->value,
]);

it('updates grid when shelf is selected', function () {
    $role = Role::create(['name' => RoleEnum::ArchiveManager->value]);
    $user = User::factory()->create();
    $user->assignRole($role);

    // Create a physical location
    $location = PhysicalLocation::create([
        'company_id' => $user->company_id,
        'full_path' => 'Bodega / Estante 05 / Entrepaño 02 / Caja 003',
        'code' => 'BOX-05-02-003',
        'structured_data' => [
            'estante' => '05',
            'entrepaño' => '02',
            'caja' => '003',
        ],
        'capacity_total' => 3000,
        'capacity_used' => 0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(PhysicalArchiveMap::class)
        ->set('selectedShelf', '05')
        ->assertSet('selectedShelf', '05')
        ->call('selectLocation', $location->id)
        ->assertSet('selectedLocationId', $location->id);
});
