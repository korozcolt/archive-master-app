<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Documento')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Información General')
                            ->schema([
                                Infolists\Components\Section::make('Detalles del Documento')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('document_number')
                                            ->label('Número de Documento'),
                                        Infolists\Components\TextEntry::make('title')
                                            ->label('Título'),
                                        Infolists\Components\TextEntry::make('description')
                                            ->label('Descripción')
                                            ->columnSpanFull(),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Fecha de Creación')
                                            ->dateTime('d/M/Y H:i'),
                                        Infolists\Components\TextEntry::make('updated_at')
                                            ->label('Última Actualización')
                                            ->dateTime('d/M/Y H:i'),
                                    ])
                                    ->columns(2),

                                Infolists\Components\Section::make('Organización')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('company.name')
                                            ->label('Empresa'),
                                        Infolists\Components\TextEntry::make('branch.name')
                                            ->label('Sucursal'),
                                        Infolists\Components\TextEntry::make('department.name')
                                            ->label('Departamento'),
                                        Infolists\Components\TextEntry::make('category_display')
                                            ->label('Categoría')
                                            ->state(fn (Document $record): string => $this->localizedModelField($record->category, 'name')),
                                    ])
                                    ->columns(2),

                                Infolists\Components\Section::make('Estado y Asignación')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status_display')
                                            ->label('Estado')
                                            ->state(fn (Document $record): string => $this->localizedModelField($record->status, 'name'))
                                            ->badge()
                                            ->color(fn ($record) => $record->status ? $record->status->color : 'gray'),
                                        Infolists\Components\TextEntry::make('priority')
                                            ->label('Prioridad')
                                            ->formatStateUsing(fn ($state): string => $this->priorityLabel($state))
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('creator_display')
                                            ->label('Creado por')
                                            ->state(fn (Document $record): string => $record->creator?->name ?? 'Sin definir'),
                                        Infolists\Components\TextEntry::make('assignee_display')
                                            ->label('Asignado a')
                                            ->state(fn (Document $record): string => $record->assignee?->name ?? 'Sin asignar'),
                                    ])
                                    ->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Contenido')
                            ->schema([
                                Infolists\Components\Section::make('Contenido del Documento')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('content')
                                            ->label('Texto extraído / contenido')
                                            ->markdown()
                                            ->placeholder('No hay contenido textual extraído disponible para este documento.')
                                            ->columnSpanFull(),
                                    ]),

                                Infolists\Components\Section::make('Archivos')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('file_path')
                                            ->label('Archivo Adjunto')
                                            ->formatStateUsing(fn ($state) => $state ? basename($state) : 'Sin archivo')
                                            ->url(fn ($record) => $record->file_path ? route('documents.download', ['id' => $record->id]) : null)
                                            ->openUrlInNewTab()
                                            ->hidden(fn ($record) => ! $record->file_path),
                                        Infolists\Components\TextEntry::make('file_path_help')
                                            ->label('Acción')
                                            ->state('Este documento no tiene archivo adjunto. Use "Nueva Versión" para cargar un archivo.')
                                            ->hidden(fn ($record) => (bool) $record->file_path),
                                    ]),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Fechas y Plazos')
                            ->schema([
                                Infolists\Components\Section::make('Fechas Importantes')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('received_at')
                                            ->label('Fecha de Recepción')
                                            ->dateTime('d/M/Y H:i'),
                                        Infolists\Components\TextEntry::make('due_at')
                                            ->label('Fecha de Vencimiento')
                                            ->dateTime('d/M/Y H:i'),
                                        Infolists\Components\TextEntry::make('completed_at')
                                            ->label('Fecha de Completado')
                                            ->dateTime('d/M/Y H:i'),
                                        Infolists\Components\TextEntry::make('archived_at')
                                            ->label('Fecha de Archivo')
                                            ->dateTime('d/M/Y H:i'),
                                    ])
                                    ->columns(2),

                                Infolists\Components\Section::make('Estado Actual')
                                    ->schema([
                                        Infolists\Components\IconEntry::make('is_confidential')
                                            ->label('Confidencial')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('is_archived')
                                            ->label('Archivado')
                                            ->boolean(),
                                        Infolists\Components\TextEntry::make('physical_location')
                                            ->label('Ubicación Física'),
                                    ])
                                    ->columns(3),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Metadatos')
                            ->schema([
                                Infolists\Components\Section::make('Metadatos y Configuración')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('metadata_empty_message')
                                            ->label('')
                                            ->state('No hay metadatos adicionales registrados para este documento.')
                                            ->hidden(fn (Document $record): bool => ! empty($record->metadata)),
                                        Infolists\Components\KeyValueEntry::make('metadata')
                                            ->label('Metadatos Adicionales')
                                            ->hidden(fn (Document $record): bool => empty($record->metadata)),
                                        Infolists\Components\TextEntry::make('settings_empty_message')
                                            ->label('')
                                            ->state('No hay configuración adicional definida para este documento.')
                                            ->hidden(fn (Document $record): bool => ! empty($record->settings)),
                                        Infolists\Components\KeyValueEntry::make('settings')
                                            ->label('Configuración')
                                            ->hidden(fn (Document $record): bool => empty($record->settings)),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $document = $this->getRecord();
        $user = Auth::user();

        $actions = [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];

        // Add change status action if there are valid transitions for the current document state
        if ($document->status_id) {
            $possibleTransitions = WorkflowDefinition::where('company_id', $document->company_id)
                ->where('from_status_id', $document->status_id)
                ->where('active', true)
                ->with('toStatus')
                ->get();

            if ($possibleTransitions->isNotEmpty()) {
                $statusOptions = $possibleTransitions
                    ->filter(fn ($transition) => $transition->toStatus !== null)
                    ->mapWithKeys(fn ($transition) => [$transition->toStatus->id => $this->localizedModelField($transition->toStatus, 'name')])
                    ->toArray();

                $actions[] = Actions\Action::make('changeStatus')
                    ->label('Cambiar Estado')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        \Filament\Forms\Components\Select::make('to_status_id')
                            ->label('Nuevo Estado')
                            ->options($statusOptions)
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos del cambio de estado')
                            ->rows(3),
                    ])
                    ->action(function (array $data) use ($document, $user): void {
                        // Obtener el estado de destino
                        $toStatus = Status::find($data['to_status_id']);
                        if (! $toStatus) {
                            return;
                        }

                        // Cambiar el estado del documento
                        $document->changeStatus($toStatus, $user, $data['comments'] ?? null);

                        // Notificación de éxito
                        \Filament\Notifications\Notification::make()
                            ->title('Estado actualizado')
                            ->body('El estado del documento ha sido actualizado correctamente.')
                            ->success()
                            ->send();

                        // Refresh the page
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $document]));
                    });
            }
        }

        // Add archive/unarchive actions
        if ($document->is_archived) {
            $actions[] = Actions\Action::make('unarchive')
                ->label('Restaurar')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->action(function () use ($document, $user): void {
                    // Desarchivar el documento
                    $document->unarchive($user);

                    // Notificación de éxito
                    \Filament\Notifications\Notification::make()
                        ->title('Documento restaurado')
                        ->body('El documento ha sido restaurado correctamente.')
                        ->success()
                        ->send();

                    // Refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $document]));
                });
        } else {
            $actions[] = Actions\Action::make('archive')
                ->label('Archivar')
                ->icon('heroicon-o-archive-box')
                ->form([
                    \Filament\Forms\Components\Textarea::make('comments')
                        ->label('Comentarios')
                        ->placeholder('Indique los motivos para archivar el documento')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($document, $user): void {
                    // Archivar el documento
                    $document->archive($user, $data['comments'] ?? null);

                    // Notificación de éxito
                    \Filament\Notifications\Notification::make()
                        ->title('Documento archivado')
                        ->body('El documento ha sido archivado correctamente.')
                        ->success()
                        ->send();

                    // Refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $document]));
                });
        }

        // Add create new version action
        $actions[] = Actions\Action::make('newVersion')
            ->label('Nueva Versión')
            ->icon('heroicon-o-document-plus')
            ->form([
                \Filament\Forms\Components\FileUpload::make('file_path')
                    ->label('Archivo')
                    ->directory('documents')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'])
                    ->maxSize(10240),
                \Filament\Forms\Components\Textarea::make('content')
                    ->label('Contenido')
                    ->rows(5),
                \Filament\Forms\Components\TextInput::make('change_summary')
                    ->label('Resumen de cambios')
                    ->required(),
            ])
            ->action(function (array $data) use ($document, $user): void {
                // Create new version
                $document->addVersion(
                    $data['content'] ?? null,
                    $data['file_path'] ?? null,
                    $user
                );

                // Notification
                \Filament\Notifications\Notification::make()
                    ->title('Nueva versión creada')
                    ->body('Se ha creado una nueva versión del documento correctamente.')
                    ->success()
                    ->send();

                // Refresh the page
                $this->redirect($this->getResource()::getUrl('view', ['record' => $document]));
            });

        $actions[] = Actions\Action::make('generateSticker')
            ->label('Generar Etiqueta')
            ->icon('heroicon-o-qr-code')
            ->form([
                \Filament\Forms\Components\Select::make('template')
                    ->label('Plantilla de etiqueta')
                    ->options([
                        'standard' => 'Estándar (50mm x 80mm)',
                        'compact' => 'Compacto (40mm x 40mm)',
                        'detailed' => 'Detallado (80mm x 100mm)',
                        'label' => 'Etiqueta (100mm x 50mm)',
                    ])
                    ->default('standard')
                    ->required(),
                \Filament\Forms\Components\Toggle::make('include_company')
                    ->label('Incluir nombre de empresa')
                    ->default(true),
                \Filament\Forms\Components\Toggle::make('include_date')
                    ->label('Incluir fecha de generación')
                    ->default(true),
            ])
            ->action(function (array $data) use ($document): void {
                $url = route('stickers.documents.download', [
                    'document' => $document->id,
                    'template' => $data['template'] ?? 'standard',
                    'options' => [
                        'include_company' => $data['include_company'] ?? true,
                        'include_date' => $data['include_date'] ?? true,
                    ],
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Etiqueta generada')
                    ->body('La etiqueta se está descargando...')
                    ->success()
                    ->send();

                $this->redirect($url, navigate: false);
            });

        // Add download options
        $actions[] = Actions\Action::make('download')
            ->label('Descargar')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(function () use ($document) {
                // Si existe el archivo, crear una URL de descarga
                if ($document->file_path) {
                    return route('documents.download', ['id' => $document->id]);
                }

                return null;
            })
            ->openUrlInNewTab()
            ->action(function () use ($document): void {
                // Si no hay archivo, mostrar notificación
                if (! $document->file_path) {
                    \Filament\Notifications\Notification::make()
                        ->title('No hay archivo')
                        ->body('Este documento no tiene un archivo adjunto para descargar.')
                        ->warning()
                        ->send();
                }
            })
            ->hidden(fn ($record) => ! $record->file_path);

        return $actions;
    }

    private function localizedModelField(mixed $model, string $field): string
    {
        if (! $model) {
            return 'Sin definir';
        }

        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        if (method_exists($model, 'getTranslation')) {
            $translated = $model->getTranslation($field, $locale, false);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }

            $fallback = $model->getTranslation($field, $fallbackLocale, false);

            if (is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        }

        $raw = method_exists($model, 'getRawOriginal') ? $model->getRawOriginal($field) : data_get($model, $field);
        $candidate = $raw ?? data_get($model, $field);

        if (is_array($candidate)) {
            return (string) ($candidate[$locale] ?? $candidate[$fallbackLocale] ?? reset($candidate) ?? 'Sin definir');
        }

        if (is_string($candidate)) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return (string) ($decoded[$locale] ?? $decoded[$fallbackLocale] ?? reset($decoded) ?? 'Sin definir');
            }

            return $candidate;
        }

        return (string) ($candidate ?? 'Sin definir');
    }

    private function priorityLabel(mixed $priority): string
    {
        if ($priority instanceof \BackedEnum) {
            $priority = (string) $priority->value;
        }

        return match ($priority) {
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
            default => $priority ?: 'Sin definir',
        };
    }
}
