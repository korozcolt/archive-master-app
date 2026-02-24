<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds default client data and leaves only one super admin user', function () {
    $this->seed(\Database\Seeders\ClientDefaultSeeder::class);

    expect(Company::query()->count())->toBeGreaterThan(0);
    expect(Category::query()->count())->toBeGreaterThan(0);
    expect(Status::query()->count())->toBeGreaterThan(0);
    expect(Tag::query()->count())->toBeGreaterThan(0);
    expect(DocumentTemplate::query()->count())->toBeGreaterThan(0);
    expect(PhysicalLocationTemplate::query()->count())->toBeGreaterThan(0);
    expect(PhysicalLocation::query()->count())->toBeGreaterThan(0);

    expect(User::query()->count())->toBe(1);

    $user = User::query()->sole();

    expect($user->email)->toBe('ing.korozco@gmail.com')
        ->and($user->name)->toBe('Kristian Orozco')
        ->and($user->phone)->toBe('3016859339')
        ->and($user->position)->toBe('Administrador del sistema')
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});
