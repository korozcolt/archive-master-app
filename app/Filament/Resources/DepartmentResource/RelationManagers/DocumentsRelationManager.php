<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use App\Enums\Priority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Documentos';

    protected static ?string $label = 'Documento';

    protected static ?string $pluralLabel = 'Documentos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Documento')
                    ->schema([
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->company_id),
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->branch_id),
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Asignación')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name', fn (Builder $query, $livewire) =>
                                $query->where('company_id', $livewire->ownerRecord->company_id)
                            )
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status_id')
                            ->label('Estado')
                            ->relationship('status', 'name', fn (Builder $query, $livewire) =>
                                $query->where('company_id', $livewire->ownerRecord->company_id)
                            )
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Asignado a')
                            ->relationship('assignee', 'name', fn (Builder $query, $livewire) =>
                                $query->where('company_id', $livewire->ownerRecord->company_id)
                                    ->where('department_id', $livewire->ownerRecord->id)
                            )
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Detalles')
                    ->schema([
                        Forms\Components\Select::make('priority')
                            ->label('Prioridad')
                            ->options(collect(Priority::cases())->pluck('value', 'value')
                                ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()]))
                            ->default('medium'),
                        Forms\Components\DateTimePicker::make('received_at')
                            ->label('Fecha de recepción')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('due_at')
                            ->label('Fecha de vencimiento'),
                        Forms\Components\Toggle::make('is_confidential')
                            ->label('Confidencial')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ubicación y Archivo')
                    ->schema([
                        Forms\Components\TextInput::make('physical_location')
                            ->label('Ubicación física')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_archived')
                            ->label('Archivado')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contenido')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Contenido')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
            })
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
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(collect(Priority::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()])),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Asignado a')
                    ->relationship('assignee', 'name', fn (Builder $query) =>
                        $query->where('department_id', $this->ownerRecord->id)
                    ),
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['department_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('archiveDocuments')
                        ->label('Archivar Documentos')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_archived' => true, 'archived_at' => now()])),
                    Tables\Actions\BulkAction::make('changeStatus')
                        ->label('Cambiar Estado')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status_id')
                                ->label('Nuevo Estado')
                                ->relationship('status', 'name', fn (Builder $query, $livewire) =>
                                    $query->where('company_id', $livewire->ownerRecord->company_id)
                                )
                                ->required(),
                            Forms\Components\Textarea::make('comments')
                                ->label('Comentarios del cambio')
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            // En un escenario real, aquí podrías registrar el cambio en workflow_histories
                            $records->each->update([
                                'status_id' => $data['status_id'],
                            ]);
                        }),
                ]),
            ]);
    }
}
