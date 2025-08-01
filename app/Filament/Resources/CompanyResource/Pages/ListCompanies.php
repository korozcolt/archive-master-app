<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Crear Rápido'),
            Actions\Action::make('createWizard')
                ->label('Crear con Asistente')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->url(fn (): string => CompanyResource::getUrl('create-wizard'))
                ->tooltip('Crear empresa paso a paso con asistente'),
        ];
    }
}
