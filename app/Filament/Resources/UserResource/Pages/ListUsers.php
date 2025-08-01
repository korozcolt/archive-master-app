<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Rápido'),
            Actions\Action::make('createWizard')
                ->label('Crear con Asistente')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->url(fn (): string => UserResource::getUrl('create-wizard'))
                ->tooltip('Crear usuario paso a paso con asistente'),
        ];
    }
}
