<?php

namespace App\Filament\Resources\RetentionScheduleResource\Pages;

use App\Filament\Resources\RetentionScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRetentionSchedule extends EditRecord
{
    protected static string $resource = RetentionScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
