<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class RecentActivity extends BaseWidget
{
    protected static ?string $heading = 'Actividad Reciente';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->whereHasMorph('subject', ['App\\Models\\Document'], function ($query) {
                        $query->where('company_id', Auth::user()->company_id);
                    })
                    ->orWhereHasMorph('causer', ['App\\Models\\User'], function ($query) {
                        $query->where('company_id', Auth::user()->company_id);
                    })
                    ->latest()
                    ->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Actividad')
                    ->formatStateUsing(function (string $state, Activity $record): string {
                        return $this->formatActivityDescription($state, $record);
                    })
                    ->html(),
                    
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipo')
                    ->formatStateUsing(function (?string $state): string {
                        return match ($state) {
                            'App\\Models\\Document' => 'Documento',
                            'App\\Models\\User' => 'Usuario',
                            'App\\Models\\Company' => 'Empresa',
                            'App\\Models\\Branch' => 'Sucursal',
                            'App\\Models\\Department' => 'Departamento',
                            default => 'Desconocido',
                        };
                    })
                    ->badge()
                    ->color(function (?string $state): string {
                        return match ($state) {
                            'App\\Models\\Document' => 'primary',
                            'App\\Models\\User' => 'success',
                            'App\\Models\\Company' => 'warning',
                            'App\\Models\\Branch' => 'info',
                            'App\\Models\\Department' => 'secondary',
                            default => 'gray',
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(fn (Activity $record): string => $record->created_at->format('d/m/Y H:i:s')),
                    
                Tables\Columns\IconColumn::make('event')
                    ->label('Evento')
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'created' => 'heroicon-o-plus-circle',
                            'updated' => 'heroicon-o-pencil-square',
                            'deleted' => 'heroicon-o-trash',
                            'restored' => 'heroicon-o-arrow-uturn-left',
                            'status_changed' => 'heroicon-o-arrow-right-circle',
                            'assigned' => 'heroicon-o-user-plus',
                            'commented' => 'heroicon-o-chat-bubble-left',
                            default => 'heroicon-o-information-circle',
                        };
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'created' => 'success',
                            'updated' => 'warning',
                            'deleted' => 'danger',
                            'restored' => 'info',
                            'status_changed' => 'primary',
                            'assigned' => 'secondary',
                            'commented' => 'gray',
                            default => 'gray',
                        };
                    })
                    ->tooltip(function (string $state): string {
                        return match ($state) {
                            'created' => 'Creado',
                            'updated' => 'Actualizado',
                            'deleted' => 'Eliminado',
                            'restored' => 'Restaurado',
                            'status_changed' => 'Estado cambiado',
                            'assigned' => 'Asignado',
                            'commented' => 'Comentario',
                            default => 'Evento',
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Ver detalles')
                    ->icon('heroicon-m-eye')
                    ->modalHeading('Detalles de la Actividad')
                    ->modalContent(function (Activity $record): \Illuminate\Contracts\View\View {
                        return view('filament.widgets.activity-details', [
                            'activity' => $record,
                            'properties' => $record->properties->toArray(),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                    
                Tables\Actions\Action::make('view_subject')
                    ->label('Ver elemento')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->visible(fn (Activity $record): bool => $record->subject !== null)
                    ->url(function (Activity $record): ?string {
                        if (!$record->subject) {
                            return null;
                        }
                        
                        return match ($record->subject_type) {
                            'App\\Models\\Document' => "/admin/documents/{$record->subject_id}",
                            'App\\Models\\User' => "/admin/users/{$record->subject_id}",
                            'App\\Models\\Company' => "/admin/companies/{$record->subject_id}",
                            'App\\Models\\Branch' => "/admin/branches/{$record->subject_id}",
                            'App\\Models\\Department' => "/admin/departments/{$record->subject_id}",
                            default => null,
                        };
                    })
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No hay actividad reciente')
            ->emptyStateDescription('La actividad del sistema aparecerá aquí.')
            ->emptyStateIcon('heroicon-o-clock')
            ->description('Últimas 20 actividades del sistema')
            ->poll('60s'); // Actualizar cada minuto
    }
    
    /**
     * Formatear la descripción de la actividad
     */
    private function formatActivityDescription(string $description, Activity $record): string
    {
        $subjectName = $record->subject?->title ?? $record->subject?->name ?? 'Elemento eliminado';
        $causerName = $record->causer?->name ?? 'Sistema';
        
        return match ($record->event) {
            'created' => "<strong>{$causerName}</strong> creó {$subjectName}",
            'updated' => "<strong>{$causerName}</strong> actualizó {$subjectName}",
            'deleted' => "<strong>{$causerName}</strong> eliminó {$subjectName}",
            'restored' => "<strong>{$causerName}</strong> restauró {$subjectName}",
            'status_changed' => $this->formatStatusChange($record),
            'assigned' => "<strong>{$causerName}</strong> asignó {$subjectName}",
            'commented' => "<strong>{$causerName}</strong> comentó en {$subjectName}",
            default => $description,
        };
    }
    
    /**
     * Formatear cambio de estado
     */
    private function formatStatusChange(Activity $record): string
    {
        $properties = $record->properties;
        $oldStatus = $properties['old']['status'] ?? 'Desconocido';
        $newStatus = $properties['attributes']['status'] ?? 'Desconocido';
        $causerName = $record->causer?->name ?? 'Sistema';
        $subjectName = $record->subject?->title ?? $record->subject?->name ?? 'Documento';
        
        return "<strong>{$causerName}</strong> cambió el estado de {$subjectName} de <span class='text-gray-600'>{$oldStatus}</span> a <span class='text-primary-600'>{$newStatus}</span>";
    }
    
    /**
     * Determinar si el widget debe ser visible
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
    
    /**
     * Configurar paginación
     */
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 20, 50];
    }
}