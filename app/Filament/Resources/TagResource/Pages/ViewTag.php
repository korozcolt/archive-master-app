<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTag extends ViewRecord
{
    use ViewRecord\Concerns\Translatable;
    
    protected static string $resource = TagResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
