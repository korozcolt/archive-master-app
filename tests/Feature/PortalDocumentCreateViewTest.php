<?php

use App\Enums\Role as RoleEnum;
use App\Models\Category;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders the upload new documents wizard with readable category and status labels', function (): void {
    app()->setLocale('es');

    $user = User::factory()->create([
        'language' => 'es',
    ]);

    $category = Category::factory()->create([
        'company_id' => $user->company_id,
        'name' => ['es' => 'Documentos Legales', 'en' => 'Legal Documents'],
    ]);

    $status = Status::factory()->create([
        'company_id' => $user->company_id,
        'name' => ['es' => 'Borrador', 'en' => 'Draft'],
    ]);

    $response = $this->actingAs($user)->get(route('documents.create'));

    $response
        ->assertSuccessful()
        ->assertSee('Subir Nuevos Documentos')
        ->assertSee('Selección de archivos')
        ->assertSee('Metadatos')
        ->assertSee('Configuración')
        ->assertSee('Revisión')
        ->assertSee('Seleccionar archivos')
        ->assertSee('Archivos cargados')
        ->assertSee('Crear Documento')
        ->assertSee('Documentos Legales')
        ->assertSee('Borrador')
        ->assertDontSee('{"es":"Documentos Legales"}')
        ->assertDontSee('{"es":"Borrador"}');

    expect($category)->not->toBeNull();
    expect($status)->not->toBeNull();
});

it('redirects archive portal users to historical upload instead of the generic create flow', function (): void {
    $user = User::factory()->create();
    $role = Role::firstOrCreate([
        'name' => RoleEnum::ArchiveManager->value,
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('documents.create'))
        ->assertRedirect(route('documents.historical.create'));
});
