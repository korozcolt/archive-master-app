<?php

namespace App\Filament\Resources\DocumentarySeriesResource\Pages;

use App\Filament\Resources\DocumentarySeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentarySeries extends EditRecord
{
    protected static string $resource = DocumentarySeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
