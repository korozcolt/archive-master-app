<?php

namespace App\Filament\Resources\WorkflowDefinitionResource\Pages;

use App\Filament\Resources\WorkflowDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowDefinition extends CreateRecord
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
