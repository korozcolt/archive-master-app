<?php

namespace App\Filament\Resources\DocumentarySubseriesResource\Pages;

use App\Filament\Resources\DocumentarySubseriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentarySubseries extends ListRecords
{
    protected static string $resource = DocumentarySubseriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
