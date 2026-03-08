<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $warningDays = data_get($data, 'settings.document_governance.warning_days');

        if (is_string($warningDays)) {
            data_set(
                $data,
                'settings.document_governance.warning_days',
                collect(explode(',', $warningDays))
                    ->map(static fn (string $day): string => trim($day))
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        unset($data['ai_setting']);

        return $data;
    }
}
