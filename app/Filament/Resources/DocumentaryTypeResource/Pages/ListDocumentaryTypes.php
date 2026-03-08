<?php

namespace App\Filament\Resources\DocumentaryTypeResource\Pages;

use App\Filament\Resources\DocumentaryTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentaryTypes extends ListRecords
{
    protected static string $resource = DocumentaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
