<?php

namespace App\Filament\Resources;

use App\Enums\Priority;
use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Resources\DocumentResource\RelationManagers;
use App\Models\Document;
use App\Models\Status;
use App\Models\Category;
use App\Models\Department;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Documento')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información Básica')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Hidden::make('created_by')
                                            ->default(Auth::id()),
                                        Forms\Components\Select::make('company_id')
                                            ->label('Empresa')
                                            ->relationship('company', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('branch_id', null)),
                                        Forms\Components\Select::make('branch_id')
                                            ->label('Sucursal')
                                            ->relationship('branch', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),
                                        Forms\Components\Select::make('department_id')
                                            ->label('Departamento')
                                            ->relationship('department', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->when($get('branch_id'), fn ($query, $branchId) =>
                                                        $query->where('branch_id', $branchId)
                                                    )
                                            )
                                            ->searchable()
                                            ->preload(),
                                        Forms\Components\Select::make('category_id')
                                            ->label('Categoría')
                                            ->relationship('category', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                            )
                                            ->searchable()
                                            ->preload(),
                                        Forms\Components\Select::make('status_id')
                                            ->label('Estado')
                                            ->relationship('status', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->where('is_initial', true)
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\Select::make('assigned_to')
                                            ->label('Asignado a')
                                            ->relationship('assignee', 'name', fn (Builder $query, Get $get) =>
                                                $query->where('company_id', $get('company_id'))
                                                    ->when($get('department_id'), fn ($query, $departmentId) =>
                                                        $query->where('department_id', $departmentId)
                                                    )
                                            )
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Título')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción')
                                            ->rows(3)
                                            ->maxLength(65535),
                                        Forms\Components\Select::make('priority')
                                            ->label('Prioridad')
                                            ->options(collect(Priority::cases())->pluck('value', 'value')
                                                ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()]))
                                            ->default('medium')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contenido')
                            ->schema([
                                Forms\Components\FileUpload::make('file')
                                    ->label('Archivo')
                                    ->directory('documents')
                                    ->preserveFilenames()
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'])
                                    ->maxSize(10240)
                                    ->columnSpanFull(),
                                Forms\Components\MarkdownEditor::make('content')
                                    ->label('Contenido')
                                    ->columnSpanFull()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('documents')
                                    ->fileAttachmentsVisibility('public'),
                                Forms\Components\TagsInput::make('tags')
                                    ->label('Etiquetas')
                                    ->separator(',')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Detalles')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('document_number')
                                            ->label('Número de documento')
                                            ->helperText('Si se deja vacío, se generará automáticamente')
                                            ->disabled()
                                            ->dehydrated(false),
                                        Forms\Components\TextInput::make('barcode')
                                            ->label('Código de barras')
                                            ->helperText('Si se deja vacío, se generará automáticamente')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('received_at')
                                            ->label('Fecha de recepción')
                                            ->default(now()),
                                        Forms\Components\DateTimePicker::make('due_at')
                                            ->label('Fecha de vencimiento'),
                                    ]),
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('physical_location')
                                            ->label('Ubicación física')
                                            ->maxLength(255),
                                        Forms\Components\Toggle::make('is_confidential')
                                            ->label('Confidencial')
                                            ->default(false),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Metadatos')
                            ->schema([
                                Forms\Components\KeyValue::make('metadata')
                                    ->label('Metadatos adicionales')
                                    ->keyLabel('Campo')
                                    ->valueLabel('Valor')
                                    ->reorderable()
                                    ->columnSpanFull(),
                                Forms\Components\KeyValue::make('settings')
                                    ->label('Configuración')
                                    ->keyLabel('Configuración')
                                    ->valueLabel('Valor')
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->status ? $record->status->color : 'gray'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignado a')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_confidential')
                    ->label('Confidencial')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archivado')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(collect(Priority::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()])),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Asignado a')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('is_confidential')
                    ->label('Confidencial')
                    ->query(fn (Builder $query): Builder => $query->where('is_confidential', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_archived')
                    ->label('Archivado')
                    ->query(fn (Builder $query): Builder => $query->where('is_archived', true))
                    ->toggle(),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNull('completed_at'))
                    ->toggle(),
                Tables\Filters\Filter::make('due_today')
                    ->label('Vencen hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('due_at', now()->toDateString())->whereNull('completed_at'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar Estado')
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading('Cambiar Estado del Documento')
                    ->form([
                        Forms\Components\Select::make('to_status_id')
                            ->label('Nuevo Estado')
                            ->options(function (Document $record): array {
                                // Obtener estados válidos para transiciones
                                if (!$record->status_id) return [];

                                // Obtener definiciones de workflow que salen del estado actual
                                $definitions = \App\Models\WorkflowDefinition::where('company_id', $record->company_id)
                                    ->where('from_status_id', $record->status_id)
                                    ->where('active', true)
                                    ->with('toStatus')
                                    ->get();

                                if ($definitions->isEmpty()) return [];

                                return $definitions->pluck('toStatus.name', 'toStatus.id')
                                    ->toArray();
                            })
                            ->required(),
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos del cambio de estado')
                            ->rows(3),
                    ])
                    ->action(function (Document $record, array $data): void {
                        // Obtener el estado de destino
                        $toStatus = Status::find($data['to_status_id']);
                        if (!$toStatus) return;

                        // Cambiar el estado del documento
                        $record->changeStatus($toStatus, Auth::user(), $data['comments'] ?? null);

                        // Notificación de éxito
                        Notification::make()
                            ->title('Estado actualizado')
                            ->body('El estado del documento ha sido actualizado correctamente.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archivar')
                    ->icon('heroicon-o-archive-box')
                    ->modalHeading('Archivar Documento')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->placeholder('Indique los motivos para archivar el documento')
                            ->rows(3),
                    ])
                    ->action(function (Document $record, array $data): void {
                        // Archivar el documento
                        $record->archive(Auth::user(), $data['comments'] ?? null);

                        // Notificación de éxito
                        Notification::make()
                            ->title('Documento archivado')
                            ->body('El documento ha sido archivado correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (Document $record): bool => $record->is_archived),
                Tables\Actions\Action::make('unarchive')
                    ->label('Restaurar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalHeading('Restaurar Documento')
                    ->requiresConfirmation()
                    ->action(function (Document $record): void {
                        // Desarchivar el documento
                        $record->unarchive(Auth::user());

                        // Notificación de éxito
                        Notification::make()
                            ->title('Documento restaurado')
                            ->body('El documento ha sido restaurado correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (Document $record): bool => !$record->is_archived),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoryRelationManager::class,
            RelationManagers\StatusRelationManager::class,
            RelationManagers\TagsRelationManager::class,
            RelationManagers\VersionsRelationManager::class,
            RelationManagers\WorkflowHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'create-wizard' => Pages\CreateDocumentWizard::route('/create-wizard'),
            'view' => Pages\ViewDocument::route('/{record}'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
