<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\Priority;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'createdDocuments';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Documentos creados';

    protected static ?string $label = 'Documento';

    protected static ?string $pluralLabel = 'Documentos';

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

                Forms\Components\Toggle::make('is_confidential')
                    ->label('Confidencial')
                    ->default(false),

                Forms\Components\Toggle::make('is_archived')
                    ->label('Archivado')
                    ->default(false),
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
                    ->sortable()
                    ->badge()
                    ->color(fn (Document $record): string => $record->status?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn (Document $record) =>
                        $record->due_at && $record->due_at < now() && !$record->completed_at
                            ? 'danger'
                            : 'gray'
                    ),
                Tables\Columns\IconColumn::make('is_confidential')
                    ->label('Confidencial')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archivado')
                    ->boolean(),
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
                Tables\Filters\Filter::make('is_confidential')
                    ->label('Confidenciales')
                    ->query(fn (Builder $query): Builder => $query->where('is_confidential', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_archived')
                    ->label('Archivados')
                    ->query(fn (Builder $query): Builder => $query->where('is_archived', true))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record): string => route('documents.download', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
