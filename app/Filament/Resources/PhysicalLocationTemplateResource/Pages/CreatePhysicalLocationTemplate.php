<?php

namespace App\Filament\Resources\PhysicalLocationTemplateResource\Pages;

use App\Filament\Resources\PhysicalLocationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhysicalLocationTemplate extends CreateRecord
{
    protected static string $resource = PhysicalLocationTemplateResource::class;

    public function getTitle(): string
    {
        return 'Crear plantilla de ubicación';
    }

    public function getBreadcrumb(): string
    {
        return 'Crear';
    }
}
