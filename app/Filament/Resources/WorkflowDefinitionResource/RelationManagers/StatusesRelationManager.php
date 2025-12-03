<?php

namespace App\Filament\Resources\WorkflowDefinitionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'statuses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Estados Relacionados';

    protected static ?string $label = 'Estado';

    protected static ?string $pluralLabel = 'Estados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\TextColumn::make('relationship_type')
                    ->label('Tipo de Relación')
                    ->getStateUsing(function ($record): string {
                        // Verificar si este estado es el origen o destino de la definición de flujo
                        if ($record->id === $this->ownerRecord->from_status_id) {
                            return 'Estado Origen';
                        } elseif ($record->id === $this->ownerRecord->to_status_id) {
                            return 'Estado Destino';
                        }
                        return 'Relacionado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Estado Origen' => 'primary',
                        'Estado Destino' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_initial')
                    ->label('Estado Inicial')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_final')
                    ->label('Estado Final')
                    ->boolean(),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('is_initial')
                    ->label('Estados iniciales')
                    ->query(fn (Builder $query): Builder => $query->where('is_initial', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_final')
                    ->label('Estados finales')
                    ->query(fn (Builder $query): Builder => $query->where('is_final', true))
                    ->toggle(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Sin acciones en masa ya que es una vista de solo lectura
            ]);
    }

    protected function getTableQuery(): Builder|null
    {
        $query = parent::getTableQuery();

        if (!$query) {
            return null;
        }

        if ($this->ownerRecord && ($this->ownerRecord->from_status_id || $this->ownerRecord->to_status_id)) {
            $query->where(function (Builder $q) {
                if ($this->ownerRecord->from_status_id) {
                    $q->where('id', $this->ownerRecord->from_status_id);
                }
                if ($this->ownerRecord->to_status_id) {
                    $q->orWhere('id', $this->ownerRecord->to_status_id);
                }
            });
        }

        return $query;
    }
}
