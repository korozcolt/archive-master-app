<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Status;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('translatable models coerce string values to valid json payloads', function () {
    $company = Company::factory()->create();

    $tag = Tag::factory()->create([
        'company_id' => $company->id,
        'name' => 'Urgente',
        'description' => 'Etiqueta inicial',
    ]);

    $category = Category::factory()->create([
        'company_id' => $company->id,
        'name' => 'Correspondencia',
        'description' => 'Categoria inicial',
    ]);

    $status = Status::factory()->create([
        'company_id' => $company->id,
        'name' => 'Pendiente',
        'description' => 'Estado inicial',
    ]);

    $tag->update(['name' => 'Interno', 'description' => 'Descripcion actualizada']);
    $category->update(['name' => 'Archivo', 'description' => 'Descripcion actualizada']);
    $status->update(['name' => 'Finalizado', 'description' => 'Descripcion actualizada']);

    foreach ([$tag->fresh(), $category->fresh(), $status->fresh()] as $model) {
        expect(json_decode($model->getRawOriginal('name'), true))->toBeArray();
        expect(json_decode($model->getRawOriginal('description'), true))->toBeArray();
    }
});
