<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\CompanyAiSetting;
use App\Services\AI\AiGateway;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditCompany extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $setting = $this->record->aiSetting;

        $data['ai_setting'] = [
            'provider' => $setting?->provider ?? 'none',
            'is_enabled' => (bool) ($setting?->is_enabled ?? false),
            'api_key_encrypted' => '',
            'daily_doc_limit' => (int) ($setting?->daily_doc_limit ?? 100),
            'max_pages_per_doc' => (int) ($setting?->max_pages_per_doc ?? 100),
            'monthly_budget_cents' => $setting?->monthly_budget_cents,
            'store_outputs' => (bool) ($setting?->store_outputs ?? true),
            'redact_pii' => (bool) ($setting?->redact_pii ?? true),
        ];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['ai_setting']);

        return $data;
    }

    protected function afterSave(): void
    {
        $input = (array) data_get($this->data, 'ai_setting', []);
        if ($input === []) {
            return;
        }

        /** @var CompanyAiSetting|null $current */
        $current = $this->record->aiSetting;
        $newApiKey = trim((string) ($input['api_key_encrypted'] ?? ''));

        CompanyAiSetting::query()->updateOrCreate(
            ['company_id' => $this->record->id],
            [
                'provider' => (string) ($input['provider'] ?? 'none'),
                'api_key_encrypted' => $newApiKey !== '' ? $newApiKey : $current?->api_key_encrypted,
                'is_enabled' => (bool) ($input['is_enabled'] ?? false),
                'monthly_budget_cents' => isset($input['monthly_budget_cents']) && $input['monthly_budget_cents'] !== ''
                    ? (int) $input['monthly_budget_cents']
                    : null,
                'daily_doc_limit' => (int) ($input['daily_doc_limit'] ?? 100),
                'max_pages_per_doc' => (int) ($input['max_pages_per_doc'] ?? 100),
                'store_outputs' => (bool) ($input['store_outputs'] ?? true),
                'redact_pii' => (bool) ($input['redact_pii'] ?? true),
            ]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\Action::make('aiObservability')
                ->label('Ver observabilidad IA')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn (): string => CompanyResource::getUrl('ai-observability', ['record' => $this->record])),
            Actions\Action::make('testAiProvider')
                ->label('Test key IA')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->action(function (AiGateway $aiGateway): void {
                    try {
                        $result = $aiGateway->testProvider($this->record, 'Texto de prueba para validar conexión del proveedor IA.');
                        $provider = strtoupper((string) ($result['status']['provider'] ?? 'N/A'));
                        $model = (string) ($result['status']['model'] ?? 'N/A');

                        Notification::make()
                            ->title('Conexión IA validada')
                            ->body("Proveedor: {$provider} | Modelo: {$model}")
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo validar la key IA')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('runAiSample')
                ->label('Run sample IA')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->action(function (AiGateway $aiGateway): void {
                    try {
                        $sampleText = 'Solicitud interna para archivo y seguimiento con prioridad alta.';
                        $result = $aiGateway->testProvider($this->record, $sampleText);
                        $preview = (string) ($result['preview']['summary_md'] ?? 'Sin preview');

                        Notification::make()
                            ->title('Sample IA ejecutado')
                            ->body(str($preview)->limit(180)->toString())
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo ejecutar sample IA')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
