<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCompany extends ViewRecord
{
    use ViewRecord\Concerns\Translatable;
    
    protected static string $resource = CompanyResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
