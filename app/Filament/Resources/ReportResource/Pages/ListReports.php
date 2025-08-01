<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use App\Services\ReportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use App\Models\Department;
use Illuminate\Support\Collection;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_quick_report')
                ->label('Generar Reporte Rápido')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->form([
                    Section::make('Reporte Rápido')
                        ->description('Genera un reporte con configuración predeterminada')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('report_type')
                                        ->label('Tipo de Reporte')
                                        ->options([
                                            'documents-by-status' => 'Documentos por Estado',
                                            'sla-compliance' => 'Cumplimiento SLA',
                                            'user-activity' => 'Actividad por Usuario',
                                            'documents-by-department' => 'Documentos por Departamento',
                                        ])
                                        ->default('documents-by-status')
                                        ->required()
                                        ->native(false)
                                        ->searchable(),
                                        
                                    Select::make('output_format')
                                        ->label('Formato')
                                        ->options([
                                            'pdf' => 'PDF',
                                            'excel' => 'Excel',
                                        ])
                                        ->default('pdf')
                                        ->required()
                                        ->native(false),
                                ]),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        $reportService = app(ReportService::class);
                        
                        // Default filters for quick report (last 30 days)
                        $filters = [
                            'date_from' => now()->subDays(30),
                            'date_to' => now(),
                        ];
                        
                        // Generate report data
                        $reportData = match ($data['report_type']) {
                            'documents-by-status' => $reportService->documentsByStatus($filters),
                            'sla-compliance' => $reportService->slaComplianceReport($filters),
                            'user-activity' => $reportService->userActivityReport($filters),
                            'documents-by-department' => $reportService->documentsByDepartment($filters),
                            default => collect()
                        };
                        
                        // Generate output
                        if ($data['output_format'] === 'excel') {
                            return $reportService->generateExcel($data['report_type'], $reportData);
                        } else {
                            return $reportService->generatePDF($data['report_type'], $reportData, $filters);
                        }
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al generar reporte')
                            ->body('Ocurrió un error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                            
                        return null;
                    }
                })
                ->modalHeading('Generar Reporte Rápido')
                ->modalSubmitActionLabel('Generar')
                ->modalWidth('2xl'),
                
            Actions\Action::make('generate_custom_report')
                ->label('Reporte Personalizado')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('success')
                ->form([
                    Section::make('Configuración del Reporte')
                        ->description('Personaliza completamente tu reporte')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('report_type')
                                        ->label('Tipo de Reporte')
                                        ->options([
                                            'documents-by-status' => 'Documentos por Estado',
                                            'sla-compliance' => 'Cumplimiento SLA',
                                            'user-activity' => 'Actividad por Usuario',
                                            'documents-by-department' => 'Documentos por Departamento',
                                        ])
                                        ->required()
                                        ->native(false)
                                        ->searchable(),
                                        
                                    Select::make('output_format')
                                        ->label('Formato de Salida')
                                        ->options([
                                            'pdf' => 'PDF',
                                            'excel' => 'Excel (XLSX)',
                                        ])
                                        ->default('pdf')
                                        ->required()
                                        ->native(false),
                                ]),
                        ]),
                        
                    Section::make('Filtros Avanzados')
                        ->description('Aplica filtros específicos para tu reporte')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    DatePicker::make('date_from')
                                        ->label('Fecha Desde')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->default(now()->subMonth()),
                                        
                                    DatePicker::make('date_to')
                                        ->label('Fecha Hasta')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->default(now()),
                                        
                                    Select::make('department_id')
                                        ->label('Departamento')
                                        ->options(Department::pluck('name', 'id'))
                                        ->searchable()
                                        ->native(false)
                                        ->placeholder('Todos los departamentos'),
                                ]),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        $reportService = app(ReportService::class);
                        
                        // Prepare filters
                        $filters = array_filter([
                            'date_from' => $data['date_from'] ?? null,
                            'date_to' => $data['date_to'] ?? null,
                            'department_id' => $data['department_id'] ?? null,
                        ]);
                        
                        // Generate report data
                        $reportData = match ($data['report_type']) {
                            'documents-by-status' => $reportService->documentsByStatus($filters),
                            'sla-compliance' => $reportService->slaComplianceReport($filters),
                            'user-activity' => $reportService->userActivityReport($filters),
                            'documents-by-department' => $reportService->documentsByDepartment($filters),
                            default => collect()
                        };
                        
                        // Generate output
                        if ($data['output_format'] === 'excel') {
                            return $reportService->generateExcel($data['report_type'], $reportData);
                        } else {
                            return $reportService->generatePDF($data['report_type'], $reportData, $filters);
                        }
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al generar reporte')
                            ->body('Ocurrió un error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                            
                        return null;
                    }
                })
                ->modalHeading('Generar Reporte Personalizado')
                ->modalSubmitActionLabel('Generar y Descargar')
                ->modalWidth('4xl'),
        ];
    }
    
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Since we don't have a real model, we'll return an empty collection
        // This is just for the interface, actual reports are generated on demand
        return \App\Models\Document::query()->whereRaw('1 = 0');
    }
    
    public function getTableRecords(): \Illuminate\Database\Eloquent\Collection
    {
        // Return empty collection since reports are generated on demand
        return \App\Models\Document::query()->whereRaw('1 = 0')->get();
    }
}