<?php

namespace App\Filament\Resources\PhysicalLocationTemplateResource\Pages;

use App\Filament\Resources\PhysicalLocationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalLocationTemplate extends EditRecord
{
    protected static string $resource = PhysicalLocationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
