<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

test('guest cannot access log viewer', function () {
    $response = $this->get('/admin/logs');

    $response->assertRedirect(route('login'));
});

test('non super admin cannot access log viewer', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $response = $this->actingAs($user)->get('/admin/logs');

    $response->assertForbidden();
});

test('super admin can access log viewer', function () {
    $user = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');

    $response = $this->actingAs($user)->get('/admin/logs');

    $response->assertOk();
});

test('super admin can access log viewer api with session auth', function () {
    $user = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');

    $response = $this->actingAs($user)->get('/admin/logs/api/files');

    $response->assertOk();
});
