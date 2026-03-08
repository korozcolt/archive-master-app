<?php

namespace App\Filament\Resources\BusinessCalendarResource\Pages;

use App\Filament\Resources\BusinessCalendarResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessCalendars extends ListRecords
{
    protected static string $resource = BusinessCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
