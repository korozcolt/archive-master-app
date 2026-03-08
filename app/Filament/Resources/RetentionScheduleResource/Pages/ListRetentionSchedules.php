<?php

namespace App\Filament\Resources\RetentionScheduleResource\Pages;

use App\Filament\Resources\RetentionScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRetentionSchedules extends ListRecords
{
    protected static string $resource = RetentionScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
