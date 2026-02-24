<?php

use App\Filament\Resources\AdvancedSearchResource;
use App\Models\Category;

it('formats translatable relation names for advanced search table columns', function (): void {
    app()->setLocale('es');

    $category = new Category;
    $category->name = [
        'es' => 'Documentos Legales',
        'en' => 'Legal Documents',
    ];

    $formatted = AdvancedSearchResource::translatedRelationName($category, '{"es":"Documentos Legales"}');

    expect($formatted)->toBe('Documentos Legales');
});

it('falls back to decoded json when relation is not loaded', function (): void {
    app()->setLocale('es');

    $formatted = AdvancedSearchResource::translatedRelationName(null, '{"es":"Aprobado","en":"Approved"}');

    expect($formatted)->toBe('Aprobado');
});
