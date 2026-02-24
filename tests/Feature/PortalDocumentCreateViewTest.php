<?php

use App\Models\Category;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
