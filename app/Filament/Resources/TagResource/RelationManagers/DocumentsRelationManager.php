<?php

namespace App\Filament\Resources\TagResource\RelationManagers;

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
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departamento')
                    ->relationship('department', 'name'),
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
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title', 'document_number']),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
