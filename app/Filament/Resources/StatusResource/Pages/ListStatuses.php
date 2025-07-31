<?php

namespace App\Filament\Resources\StatusResource\Pages;

use App\Filament\Resources\StatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStatuses extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
