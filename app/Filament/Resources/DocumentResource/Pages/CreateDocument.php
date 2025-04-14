<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the current user as creator if not already set
        $data['created_by'] = $data['created_by'] ?? Auth::id();

        // If company_id is not set, use the current user's company
        if (empty($data['company_id']) && Auth::user() && Auth::user()->company_id) {
            $data['company_id'] = Auth::user()->company_id;
        }

        // If branch_id is not set, use the current user's branch
        if (empty($data['branch_id']) && Auth::user() && Auth::user()->branch_id) {
            $data['branch_id'] = Auth::user()->branch_id;
        }

        // If department_id is not set, use the current user's department
        if (empty($data['department_id']) && Auth::user() && Auth::user()->department_id) {
            $data['department_id'] = Auth::user()->department_id;
        }

        return $data;
    }
}
