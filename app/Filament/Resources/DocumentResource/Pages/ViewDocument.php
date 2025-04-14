<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Enums\Priority;

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
                                        Infolists\Components\TextEntry::make('category.name')
                                            ->label('Categoría'),
                                    ])
                                    ->columns(2),

                                Infolists\Components\Section::make('Estado y Asignación')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status.name')
                                            ->label('Estado')
                                            ->badge()
                                            ->color(fn ($record) => $record->status ? $record->status->color : 'gray'),
                                        Infolists\Components\TextEntry::make('priority')
                                            ->label('Prioridad')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => Priority::from($state)->getLabel()),
                                        Infolists\Components\TextEntry::make('creator.name')
                                            ->label('Creado por'),
                                        Infolists\Components\TextEntry::make('assignee.name')
                                            ->label('Asignado a'),
                                    ])
                                    ->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Contenido')
                            ->schema([
                                Infolists\Components\Section::make('Contenido del Documento')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('content')
                                            ->label('Contenido')
                                            ->markdown()
                                            ->columnSpanFull(),
                                    ]),

                                Infolists\Components\Section::make('Archivos')
                                    ->schema([
                                        Infolists\Components\ImageEntry::make('file')
                                            ->label('Archivo')
                                            ->visibility('public')
                                            ->hidden(fn ($record) => !$record->file || !str_starts_with(mime_content_type(storage_path('app/public/' . $record->file)), 'image/')),

                                        Infolists\Components\TextEntry::make('file')
                                            ->label('Archivo Adjunto')
                                            ->formatStateUsing(fn ($state) => $state ? basename($state) : 'Sin archivo')
                                            ->url(fn ($record) => $record->file ? url('storage/' . $record->file) : null)
                                            ->openUrlInNewTab()
                                            ->hidden(fn ($record) => !$record->file || str_starts_with(mime_content_type(storage_path('app/public/' . $record->file)), 'image/')),
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
                                        Infolists\Components\KeyValueEntry::make('metadata')
                                            ->label('Metadatos Adicionales'),
                                        Infolists\Components\KeyValueEntry::make('settings')
                                            ->label('Configuración'),
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
                $statusOptions = $possibleTransitions->pluck('toStatus.name', 'toStatus.id')->toArray();

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
                        if (!$toStatus) return;

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
                \Filament\Forms\Components\FileUpload::make('file')
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
                    $data['file'] ?? null,
                    $user,
                    $data['change_summary'] ?? 'Nueva versión'
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

        // Add download options
        $actions[] = Actions\Action::make('download')
            ->label('Descargar')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(function () use ($document) {
                // Si existe el archivo, crear una URL de descarga
                if ($document->file) {
                    return route('documents.download', ['id' => $document->id]);
                }

                return null;
            })
            ->openUrlInNewTab()
            ->action(function () use ($document): void {
                // Si no hay archivo, mostrar notificación
                if (!$document->file) {
                    \Filament\Notifications\Notification::make()
                        ->title('No hay archivo')
                        ->body('Este documento no tiene un archivo adjunto para descargar.')
                        ->warning()
                        ->send();
                }
            })
            ->hidden(fn ($record) => !$record->file);

        return $actions;
    }
}
