<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewDocumentTemplate extends ViewRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Ver Plantilla de Documento';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Ver Plantilla de Documento';
    }

    public function getBreadcrumb(): string
    {
        return 'Ver';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Editar'),
            Actions\DeleteAction::make()->label('Eliminar'),
            Actions\RestoreAction::make()->label('Restaurar'),
            Actions\ForceDeleteAction::make()->label('Eliminar definitivamente'),
        ];
    }
}
