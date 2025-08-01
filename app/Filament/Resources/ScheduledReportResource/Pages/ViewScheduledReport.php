<?php

namespace App\Filament\Resources\ScheduledReportResource\Pages;

use App\Filament\Resources\ScheduledReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewScheduledReport extends ViewRecord
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('run_now')
                ->label('Ejecutar Ahora')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    \App\Jobs\ProcessScheduledReports::dispatch();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Reporte en Proceso')
                        ->body('El reporte se está generando y será enviado por email.')
                        ->success()
                        ->send();
                }),
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información General')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre del Reporte')
                                    ->weight(FontWeight::Bold),
                                    
                                TextEntry::make('user.name')
                                    ->label('Usuario'),
                            ]),
                            
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Configuración del Reporte')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('report_config.report_type')
                                    ->label('Tipo de Reporte')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'documents' => 'Documentos',
                                        'users' => 'Usuarios',
                                        'departments' => 'Departamentos',
                                        default => $state,
                                    }),
                                    
                                TextEntry::make('report_config.export_format')
                                    ->label('Formato de Exportación')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pdf' => 'PDF',
                                        'xlsx' => 'Excel',
                                        'csv' => 'CSV',
                                        default => $state,
                                    }),
                            ]),
                            
                        TextEntry::make('report_config.columns')
                            ->label('Columnas a Incluir')
                            ->listWithLineBreaks()
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Programación')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('schedule_frequency')
                                    ->label('Frecuencia')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'daily' => 'Diario',
                                        'weekly' => 'Semanal',
                                        'monthly' => 'Mensual',
                                        'quarterly' => 'Trimestral',
                                        default => $state,
                                    })
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'daily' => 'success',
                                        'weekly' => 'info',
                                        'monthly' => 'warning',
                                        'quarterly' => 'danger',
                                        default => 'gray',
                                    }),
                                    
                                TextEntry::make('schedule_time')
                                    ->label('Hora de Ejecución')
                                    ->time('H:i'),
                                    
                                TextEntry::make('schedule_day_of_week')
                                    ->label('Día de la Semana')
                                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                                        0 => 'Domingo',
                                        1 => 'Lunes',
                                        2 => 'Martes',
                                        3 => 'Miércoles',
                                        4 => 'Jueves',
                                        5 => 'Viernes',
                                        6 => 'Sábado',
                                        default => 'No especificado',
                                    })
                                    ->visible(fn ($record) => $record->schedule_frequency === 'weekly'),
                            ]),
                            
                        TextEntry::make('schedule_day_of_month')
                            ->label('Día del Mes')
                            ->visible(fn ($record) => in_array($record->schedule_frequency, ['monthly', 'quarterly'])),
                    ]),
                    
                Section::make('Destinatarios de Email')
                    ->schema([
                        TextEntry::make('email_recipients')
                            ->label('Emails de Destinatarios')
                            ->listWithLineBreaks()
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Estado y Ejecución')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Estado')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                    
                                TextEntry::make('last_run_at')
                                    ->label('Última Ejecución')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('Nunca'),
                                    
                                TextEntry::make('next_run_at')
                                    ->label('Próxima Ejecución')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('No programada'),
                            ]),
                    ]),
                    
                Section::make('Información de Auditoría')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}