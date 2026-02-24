<?php

use App\Filament\Resources\PhysicalLocationResource;
use App\Filament\Resources\PhysicalLocationTemplateResource;
use App\Models\PhysicalLocationTemplate;

it('normalizes structured data for key value fields into strings', function (): void {
    $normalized = PhysicalLocationResource::normalizeStructuredDataForKeyValue([
        'edificio' => 'A',
        'piso' => 3,
        'sala' => ['archivo', 'central'],
        'null_field' => null,
    ]);

    expect($normalized)->toBe([
        'edificio' => 'A',
        'piso' => '3',
        'sala' => 'archivo, central',
        'null_field' => '',
    ]);
});

it('builds structured data defaults from template levels', function (): void {
    $template = new PhysicalLocationTemplate([
        'levels' => [
            ['name' => 'Edificio', 'order' => 2],
            ['name' => 'Piso', 'order' => 1],
            ['name' => 'Caja', 'order' => 3],
        ],
    ]);

    $defaults = PhysicalLocationResource::buildStructuredDataDefaultsFromTemplate($template);

    expect($defaults)->toBe([
        'piso' => '',
        'edificio' => '',
        'caja' => '',
    ]);
});

it('defines resource labels in spanish for physical locations modules', function (): void {
    expect(PhysicalLocationResource::getModelLabel())->toBe('ubicación física');
    expect(PhysicalLocationResource::getPluralModelLabel())->toBe('ubicaciones físicas');
    expect(PhysicalLocationTemplateResource::getModelLabel())->toBe('plantilla de ubicación');
    expect(PhysicalLocationTemplateResource::getPluralModelLabel())->toBe('plantillas de ubicación');
});
