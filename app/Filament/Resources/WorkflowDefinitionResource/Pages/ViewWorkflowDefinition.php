<?php

namespace App\Filament\Resources\WorkflowDefinitionResource\Pages;

use App\Filament\Resources\WorkflowDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkflowDefinition extends ViewRecord
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
