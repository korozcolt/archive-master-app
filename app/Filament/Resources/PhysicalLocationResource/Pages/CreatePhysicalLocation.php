<?php

namespace App\Filament\Resources\PhysicalLocationResource\Pages;

use App\Filament\Resources\PhysicalLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhysicalLocation extends CreateRecord
{
    protected static string $resource = PhysicalLocationResource::class;

    public function getTitle(): string
    {
        return 'Crear ubicación física';
    }

    public function getBreadcrumb(): string
    {
        return 'Crear';
    }
}
