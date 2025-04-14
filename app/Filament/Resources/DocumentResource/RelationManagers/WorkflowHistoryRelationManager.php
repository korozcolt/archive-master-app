<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'workflowHistory';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Historial de cambios de estado';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('Estado anterior')
                    ->placeholder('Estado inicial'),
                Tables\Columns\IconColumn::make('arrow')
                    ->label('')
                    ->state('heroicon-o-arrow-right')
                    ->icon('heroicon-o-arrow-right'),
                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('Nuevo estado'),
                Tables\Columns\TextColumn::make('performer.name')
                    ->label('Realizado por'),
                Tables\Columns\TextColumn::make('comments')
                    ->label('Comentarios')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('time_spent')
                    ->label('Tiempo transcurrido')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) return 'N/A';

                        $hours = floor($state / 60);
                        $minutes = $state % 60;

                        if ($hours > 0) {
                            return "{$hours}h {$minutes}m";
                        }

                        return "{$minutes}m";
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No header actions for history
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }
}
