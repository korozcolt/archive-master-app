<?php

namespace App\Filament\Resources\PhysicalLocationResource\Pages;

use App\Filament\Resources\PhysicalLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalLocations extends ListRecords
{
    protected static string $resource = PhysicalLocationResource::class;

    public function getTitle(): string
    {
        return 'Ubicaciones físicas';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva ubicación física'),
        ];
    }
}
