<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RecentDocuments extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Document::query()
                    ->where('company_id', Auth::user()->company_id)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Document $record): ?string {
                        return strlen($record->title) > 50 ? $record->title : null;
                    }),
                    
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        if (is_array($state)) {
                            return $record->status->getTranslation('name', app()->getLocale());
                        }
                        return $state;
                    })
                    ->color(function (Document $record): string {
                        $statusColor = $record->status instanceof \App\Models\Status ? ($record->status->color ?? 'gray') : 'gray';
                        return match ($statusColor) {
                            'red' => 'danger',
                            'yellow' => 'warning', 
                            'green' => 'success',
                            'blue' => 'info',
                            'purple' => 'primary',
                            default => 'gray',
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->category) return '-';
                        if (is_array($state)) {
                            return $record->category->getTranslation('name', app()->getLocale());
                        }
                        return $state;
                    })
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignado a')
                    ->default('-')
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Document $record): string => '/admin/documents/' . $record->id)
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Document $record): string => '/admin/documents/' . $record->id . '/edit')
                    ->visible(fn (Document $record): bool => Auth::user()->can('update', $record)),
            ])
            ->emptyStateHeading('No hay documentos recientes')
            ->emptyStateDescription('Los documentos aparecerán aquí una vez que se creen.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->heading('Documentos Recientes')
            ->description('Los 10 documentos más recientes de tu empresa')
            ->headerActions([
                Tables\Actions\Action::make('viewAll')
                    ->label('Ver todos')
                    ->icon('heroicon-m-arrow-right')
                    ->url('/admin/documents')
                    ->color('primary'),
            ]);
    }
    
    /**
     * Determinar si el widget debe ser visible
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}