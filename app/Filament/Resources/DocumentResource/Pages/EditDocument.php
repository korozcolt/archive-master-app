<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        $document = $this->getRecord();
        $user = Auth::user();

        $actions = [
            Actions\ViewAction::make(),
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
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $document]));
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
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $document]));
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
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $document]));
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
                $this->redirect($this->getResource()::getUrl('edit', ['record' => $document]));
            });

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
