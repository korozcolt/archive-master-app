<?php

namespace App\Filament\Resources\DocumentaryTypeResource\Pages;

use App\Filament\Resources\DocumentaryTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentaryType extends EditRecord
{
    protected static string $resource = DocumentaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
