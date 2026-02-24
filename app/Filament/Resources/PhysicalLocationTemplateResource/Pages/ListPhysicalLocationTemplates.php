<?php

namespace App\Filament\Resources\PhysicalLocationTemplateResource\Pages;

use App\Filament\Resources\PhysicalLocationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalLocationTemplates extends ListRecords
{
    protected static string $resource = PhysicalLocationTemplateResource::class;

    public function getTitle(): string
    {
        return 'Plantillas de ubicación';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva plantilla de ubicación'),
        ];
    }
}
