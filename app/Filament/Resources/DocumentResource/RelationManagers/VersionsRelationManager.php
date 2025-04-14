<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $recordTitleAttribute = 'version_number';

    protected static ?string $title = 'Versiones';

    protected static ?string $label = 'Versión';

    protected static ?string $pluralLabel = 'Versiones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('version_number')
                    ->label('Número de Versión')
                    ->disabled()
                    ->required(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Archivo')
                    ->directory('documents/versions')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'])
                    ->maxSize(10240),
                Forms\Components\Textarea::make('content')
                    ->label('Contenido')
                    ->rows(5),
                Forms\Components\TextInput::make('change_summary')
                    ->label('Resumen de Cambios')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_current')
                    ->label('Versión Actual')
                    ->default(false),
                Forms\Components\KeyValue::make('metadata')
                    ->label('Metadatos')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Versión')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->label('Versión Actual')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/M/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creada por')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_summary')
                    ->label('Resumen de Cambios')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('Archivo')
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : 'Sin archivo')
                    ->url(fn ($record) => $record->file_path ? route('document.versions.download', ['id' => $record->id]) : null)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn ($state) => $state ? $this->formatFileSize($state) : '')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('file_type')
                    ->label('Tipo')
                    ->toggleable(),
            ])
            ->defaultSort('version_number', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_current')
                    ->label('Estado')
                    ->options([
                        '1' => 'Versión Actual',
                        '0' => 'Versión Antigua',
                    ]),
                Tables\Filters\Filter::make('with_file')
                    ->label('Con archivo')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('file_path'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva Versión')
                    ->modalHeading('Crear Nueva Versión del Documento')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set document_id
                        $data['document_id'] = $this->ownerRecord->id;

                        // Set created_by
                        $data['created_by'] = Auth::id();

                        // Calculate version number
                        $lastVersion = $this->ownerRecord->versions()->max('version_number') ?? 0;
                        $data['version_number'] = $lastVersion + 1;

                        // If marked as current, update other versions
                        if (isset($data['is_current']) && $data['is_current']) {
                            $this->ownerRecord->versions()->update(['is_current' => false]);
                        }

                        return $data;
                    })
                    // Use only fields needed for creation
                    ->form([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Archivo')
                            ->directory('documents/versions')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'])
                            ->maxSize(10240),
                        Forms\Components\Textarea::make('content')
                            ->label('Contenido')
                            ->rows(5),
                        Forms\Components\TextInput::make('change_summary')
                            ->label('Resumen de Cambios')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_current')
                            ->label('Establecer como Versión Actual')
                            ->default(true),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('setCurrent')
                    ->label('Establecer como Actual')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // Update all versions to not current
                        $this->ownerRecord->versions()->update(['is_current' => false]);

                        // Set this version as current
                        $record->update(['is_current' => true]);

                        // Show success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Versión Actualizada')
                            ->body('Esta versión ha sido establecida como la versión actual del documento.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => !$record->is_current),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->file_path ? route('document.versions.download', ['id' => $record->id]) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->file_path),
            ])
            ->bulkActions([
                // No bulk actions needed
            ]);
    }

    protected function canCreate(): bool
    {
        return true;
    }

    protected function canEdit(Model $record): bool
    {
        return false; // Versions are immutable
    }

    protected function canDelete(Model $record): bool
    {
        return false; // We don't want to delete versions
    }

    // Helper method to format file size
    private function formatFileSize($bytes)
    {
        if ($bytes == 0) {
            return '0 Bytes';
        }

        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
    }
}
