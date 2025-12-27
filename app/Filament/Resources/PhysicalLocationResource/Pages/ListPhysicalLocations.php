<?php

namespace App\Filament\Resources\PhysicalLocationResource\Pages;

use App\Filament\Resources\PhysicalLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalLocations extends ListRecords
{
    protected static string $resource = PhysicalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
