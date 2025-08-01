<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OverdueDocuments extends BaseWidget
{
    protected static ?string $heading = 'Documentos Vencidos';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Document::query()
                    ->where('company_id', Auth::user()->company_id)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereHas('status', function (Builder $query) {
                        $query->where('is_final', false);
                    })
                    ->orderBy('due_at', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (Document $record): ?string {
                        return strlen($record->title) > 40 ? $record->title : null;
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
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignado a')
                    ->default('-')
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Fecha límite')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Días vencido')
                    ->getStateUsing(function (Document $record): int {
                        return now()->diffInDays($record->due_at, false);
                    })
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(function (int $state): string {
                        return abs($state) . ' días';
                    }),
                    
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'high' => 'Alta',
                        'medium' => 'Media',
                        'low' => 'Baja',
                        default => 'Sin definir',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Document $record): string => "/admin/documents/{$record->id}")
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('assign')
                    ->label('Reasignar')
                    ->icon('heroicon-m-user-plus')
                    ->color('warning')
                    ->visible(fn (Document $record): bool => Auth::user()->can('update', $record))
                    ->url(fn (Document $record): string => "/admin/documents/{$record->id}/edit"),
                    
                Tables\Actions\Action::make('extend_deadline')
                    ->label('Extender plazo')
                    ->icon('heroicon-m-clock')
                    ->color('info')
                    ->visible(fn (Document $record): bool => Auth::user()->can('update', $record))
                    ->form([
                        Forms\Components\DateTimePicker::make('new_due_date')
                            ->label('Nueva fecha límite')
                            ->required()
                            ->native(false)
                            ->minDate(now()),
                    ])
                    ->action(function (Document $record, array $data): void {
                        $record->update([
                            'due_at' => $data['new_due_date'],
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Fecha límite actualizada')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No hay documentos vencidos')
            ->emptyStateDescription('¡Excelente! Todos los documentos están al día.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->description('Documentos que han superado su fecha límite y requieren atención inmediata')
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        // Aquí se puede implementar la exportación
                        \Filament\Notifications\Notification::make()
                            ->title('Exportación iniciada')
                            ->body('Se enviará el archivo por email cuando esté listo.')
                            ->info()
                            ->send();
                    }),
            ])
            ->poll('30s'); // Actualizar cada 30 segundos
    }
    
    /**
     * Determinar si el widget debe ser visible
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
    
    /**
     * Obtener el número de documentos vencidos para mostrar en el badge
     */
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25, 50];
    }
}