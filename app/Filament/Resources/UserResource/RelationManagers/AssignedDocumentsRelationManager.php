<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\Priority;
use App\Models\Document;
use App\Models\Status;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class AssignedDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedDocuments';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Documentos asignados';

    protected static ?string $label = 'Documento asignado';

    protected static ?string $pluralLabel = 'Documentos asignados';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),

                Forms\Components\Select::make('status_id')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('priority')
                    ->label('Prioridad')
                    ->options(collect(Priority::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()]))
                    ->default(Priority::Medium->value),

                Forms\Components\DateTimePicker::make('due_at')
                    ->label('Fecha de vencimiento'),
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
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn (Document $record): string => $record->status?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => match($state) {
                        Priority::Low->value => 'success',
                        Priority::Medium->value => 'info',
                        Priority::High->value => 'warning',
                        Priority::Urgent->value => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn (Document $record) =>
                        $record->due_at && $record->due_at < now() && !$record->completed_at
                            ? 'danger'
                            : 'gray'
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(collect(Priority::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Priority::from($value)->getLabel()])),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('due_at')
                        ->whereNull('completed_at')
                        ->where('due_at', '<', now()))
                    ->toggle(),
            ])
            ->headerActions([
                // No necesitamos crear documentos desde aquí
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record): string => route('documents.download', $record->id))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar estado')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status_id')
                            ->label('Nuevo estado')
                            ->options(function (Document $record) {
                                $currentStatus = $record->status;
                                if (!$currentStatus) {
                                    return Status::where('company_id', $record->company_id)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                }

                                return $currentStatus->getNextStatuses()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required(),
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->rows(3),
                    ])
                    ->action(function (Document $record, array $data): void {
                        $status = Status::findOrFail($data['status_id']);
                        $record->changeStatus($status, Auth::user(), $data['comments'] ?? null);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('changeStatusBulk')
                        ->label('Cambiar estado')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status_id')
                                ->label('Nuevo estado')
                                ->options(function () {
                                    $companyId = Auth::user()->company_id;
                                    return Status::where('company_id', $companyId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required(),
                            Forms\Components\Textarea::make('comments')
                                ->label('Comentarios')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $status = Status::findOrFail($data['status_id']);
                            foreach ($records as $record) {
                                if ($record->company_id === Auth::user()->company_id || Auth::user()->hasRole('super_admin')) {
                                    $record->changeStatus($status, Auth::user(), $data['comments'] ?? null);
                                }
                            }
                        }),
                ]),
            ]);
    }
}
