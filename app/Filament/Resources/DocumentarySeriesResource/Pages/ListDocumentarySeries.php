<?php

namespace App\Filament\Resources\DocumentarySeriesResource\Pages;

use App\Filament\Resources\DocumentarySeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentarySeries extends ListRecords
{
    protected static string $resource = DocumentarySeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
