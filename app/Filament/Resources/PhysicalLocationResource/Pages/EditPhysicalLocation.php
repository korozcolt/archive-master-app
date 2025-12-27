<?php

namespace App\Filament\Resources\PhysicalLocationResource\Pages;

use App\Filament\Resources\PhysicalLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalLocation extends EditRecord
{
    protected static string $resource = PhysicalLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
