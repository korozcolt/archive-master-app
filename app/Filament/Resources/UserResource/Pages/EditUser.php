<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $sanitizedRoles = [];

    protected function beforeSave(): void
    {
        if (isset($this->data['roles']) && is_array($this->data['roles'])) {
            $this->sanitizedRoles = UserResource::sanitizeAssignableRoles($this->data['roles']);
            $this->data['roles'] = $this->sanitizedRoles;
        }
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles($this->sanitizedRoles);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
