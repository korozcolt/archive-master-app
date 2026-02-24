<?php

namespace App\Filament\Resources\PhysicalLocationTemplateResource\Pages;

use App\Filament\Resources\PhysicalLocationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalLocationTemplate extends EditRecord
{
    protected static string $resource = PhysicalLocationTemplateResource::class;

    public function getTitle(): string
    {
        return 'Editar plantilla de ubicación';
    }

    public function getBreadcrumb(): string
    {
        return 'Editar';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Eliminar'),
        ];
    }
}
