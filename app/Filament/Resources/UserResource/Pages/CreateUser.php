<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $sanitizedRoles = [];

    protected function beforeCreate(): void
    {
        if (isset($this->data['roles']) && is_array($this->data['roles'])) {
            $this->sanitizedRoles = UserResource::sanitizeAssignableRoles($this->data['roles']);
            $this->data['roles'] = $this->sanitizedRoles;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si el usuario no es super_admin y no tiene company_id asignado,
        // usar la company_id del usuario actual
        if (! Auth::user()->hasRole('super_admin') && empty($data['company_id'])) {
            $data['company_id'] = Auth::user()->company_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles($this->sanitizedRoles);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
