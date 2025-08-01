<?php

namespace App\Filament\Resources\CustomReportResource\Pages;

use App\Filament\Resources\CustomReportResource;
use App\Services\ReportBuilderService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ListCustomReports extends ListRecords
{
    protected static string $resource = CustomReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Reporte Personalizado')
                ->icon('heroicon-o-plus'),
            
            Actions\Action::make('quick_reports')
                ->label('Reportes Rápidos')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('quick_report_type')
                        ->label('Tipo de Reporte Rápido')
                        ->required()
                        ->options([
                            'documents_last_week' => 'Documentos - Última Semana',
                            'documents_last_month' => 'Documentos - Último Mes',
                            'sla_compliance_month' => 'Cumplimiento SLA - Mes Actual',
                            'user_productivity_month' => 'Productividad Usuarios - Mes Actual',
                            'department_summary_quarter' => 'Resumen Departamentos - Trimestre',
                            'overdue_documents' => 'Documentos Vencidos',
                            'pending_approvals' => 'Aprobaciones Pendientes'
                        ]),
                    
                    Forms\Components\Select::make('export_format')
                        ->label('Formato')
                        ->required()
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                            'csv' => 'CSV'
                        ])
                        ->default('pdf'),
                ])
                ->action(function (array $data) {
                    $this->generateQuickReport($data['quick_report_type'], $data['export_format']);
                }),
            
            Actions\Action::make('saved_templates')
                ->label('Plantillas Guardadas')
                ->icon('heroicon-o-bookmark')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('template')
                        ->label('Seleccionar Plantilla')
                        ->options([
                            'monthly_summary' => 'Resumen Mensual Completo',
                            'weekly_productivity' => 'Productividad Semanal',
                            'quarterly_analysis' => 'Análisis Trimestral',
                            'department_comparison' => 'Comparación entre Departamentos',
                            'user_performance' => 'Rendimiento de Usuarios'
                        ]),
                    
                    Forms\Components\DatePicker::make('date_from')
                        ->label('Fecha Desde')
                        ->default(now()->subMonth()),
                    
                    Forms\Components\DatePicker::make('date_to')
                        ->label('Fecha Hasta')
                        ->default(now()),
                    
                    Forms\Components\Select::make('export_format')
                        ->label('Formato')
                        ->required()
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                            'csv' => 'CSV'
                        ])
                        ->default('pdf'),
                ])
                ->action(function (array $data) {
                    $this->generateFromTemplate(
                        $data['template'],
                        $data['date_from'],
                        $data['date_to'],
                        $data['export_format']
                    );
                }),
        ];
    }

    /**
     * Get table records - return empty collection since we're working with dynamic reports
     */
    public function getTableRecords(): \Illuminate\Database\Eloquent\Collection
    {
        // Return empty collection since we're dealing with dynamic reports
        return new \Illuminate\Database\Eloquent\Collection();
    }

    /**
     * Generate quick report based on predefined configurations
     */
    protected function generateQuickReport(string $type, string $format): void
    {
        try {
            $builder = app(ReportBuilderService::class);
            $config = $this->getQuickReportConfig($type);
            
            // Configure builder based on quick report type
            $builder->setReportType($config['report_type']);
            
            if (isset($config['date_from']) && isset($config['date_to'])) {
                $builder->setDateRange($config['date_from'], $config['date_to']);
            }
            
            if (isset($config['filters'])) {
                foreach ($config['filters'] as $filter) {
                    $builder->addFilter($filter['field'], $filter['operator'], $filter['value']);
                }
            }
            
            if (isset($config['columns'])) {
                $builder->setColumns($config['columns']);
            }
            
            if (isset($config['group_by'])) {
                foreach ($config['group_by'] as $group) {
                    $builder->groupBy($group);
                }
            }
            
            if (isset($config['order_by'])) {
                foreach ($config['order_by'] as $order) {
                    $builder->orderBy($order['field'], $order['direction']);
                }
            }
            
            // Generate report
            $filePath = $builder->export($format);
            
            // Store file path in session for potential download
            session(['generated_report_path' => $filePath]);
            
            Notification::make()
                ->title('Reporte rápido generado')
                ->body('El reporte "' . $config['name'] . '" ha sido generado exitosamente.')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al generar reporte rápido')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate report from saved template
     */
    protected function generateFromTemplate(string $template, ?string $dateFrom, ?string $dateTo, string $format): void
    {
        try {
            $builder = app(ReportBuilderService::class);
            $config = $this->getTemplateConfig($template);
            
            // Configure builder based on template
            $builder->setReportType($config['report_type']);
            
            // Use provided dates or template defaults
            $from = $dateFrom ? Carbon::parse($dateFrom) : $config['date_from'];
            $to = $dateTo ? Carbon::parse($dateTo) : $config['date_to'];
            $builder->setDateRange($from, $to);
            
            if (isset($config['filters'])) {
                foreach ($config['filters'] as $filter) {
                    $builder->addFilter($filter['field'], $filter['operator'], $filter['value']);
                }
            }
            
            if (isset($config['columns'])) {
                $builder->setColumns($config['columns']);
            }
            
            if (isset($config['group_by'])) {
                foreach ($config['group_by'] as $group) {
                    $builder->groupBy($group);
                }
            }
            
            if (isset($config['order_by'])) {
                foreach ($config['order_by'] as $order) {
                    $builder->orderBy($order['field'], $order['direction']);
                }
            }
            
            // Generate report
            $filePath = $builder->export($format);
            
            // Store file path in session for potential download
            session(['generated_report_path' => $filePath]);
            
            Notification::make()
                ->title('Reporte de plantilla generado')
                ->body('El reporte "' . $config['name'] . '" ha sido generado exitosamente.')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al generar reporte de plantilla')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get configuration for quick reports
     */
    protected function getQuickReportConfig(string $type): array
    {
        $configs = [
            'documents_last_week' => [
                'name' => 'Documentos - Última Semana',
                'report_type' => 'documents',
                'date_from' => now()->subWeek(),
                'date_to' => now(),
                'columns' => ['title', 'status_id', 'department_id', 'user_id', 'created_at'],
                'order_by' => [['field' => 'created_at', 'direction' => 'desc']]
            ],
            'documents_last_month' => [
                'name' => 'Documentos - Último Mes',
                'report_type' => 'documents',
                'date_from' => now()->subMonth()->startOfMonth(),
                'date_to' => now()->subMonth()->endOfMonth(),
                'columns' => ['title', 'status_id', 'department_id', 'user_id', 'created_at', 'completed_at'],
                'group_by' => ['status_id'],
                'order_by' => [['field' => 'created_at', 'direction' => 'desc']]
            ],
            'sla_compliance_month' => [
                'name' => 'Cumplimiento SLA - Mes Actual',
                'report_type' => 'documents',
                'date_from' => now()->startOfMonth(),
                'date_to' => now(),
                'filters' => [
                    ['field' => 'due_date', 'operator' => 'not_null', 'value' => null]
                ],
                'columns' => ['title', 'due_date', 'completed_at', 'status_id', 'department_id'],
                'order_by' => [['field' => 'due_date', 'direction' => 'asc']]
            ],
            'user_productivity_month' => [
                'name' => 'Productividad Usuarios - Mes Actual',
                'report_type' => 'documents',
                'date_from' => now()->startOfMonth(),
                'date_to' => now(),
                'columns' => ['user_id', 'status_id', 'created_at', 'completed_at'],
                'group_by' => ['user_id', 'status_id'],
                'order_by' => [['field' => 'user_id', 'direction' => 'asc']]
            ],
            'department_summary_quarter' => [
                'name' => 'Resumen Departamentos - Trimestre',
                'report_type' => 'documents',
                'date_from' => now()->startOfQuarter(),
                'date_to' => now(),
                'columns' => ['department_id', 'status_id', 'priority', 'created_at'],
                'group_by' => ['department_id', 'status_id'],
                'order_by' => [['field' => 'department_id', 'direction' => 'asc']]
            ],
            'overdue_documents' => [
                'name' => 'Documentos Vencidos',
                'report_type' => 'documents',
                'filters' => [
                    ['field' => 'due_date', 'operator' => '<', 'value' => now()->toDateString()],
                    ['field' => 'completed_at', 'operator' => 'null', 'value' => null]
                ],
                'columns' => ['title', 'due_date', 'status_id', 'department_id', 'user_id', 'priority'],
                'order_by' => [['field' => 'due_date', 'direction' => 'asc']]
            ],
            'pending_approvals' => [
                'name' => 'Aprobaciones Pendientes',
                'report_type' => 'documents',
                'filters' => [
                    ['field' => 'status_id', 'operator' => 'in', 'value' => [2, 3]] // Assuming 2,3 are pending status IDs
                ],
                'columns' => ['title', 'status_id', 'department_id', 'user_id', 'created_at', 'due_date'],
                'order_by' => [['field' => 'created_at', 'direction' => 'asc']]
            ]
        ];
        
        return $configs[$type] ?? [];
    }

    /**
     * Get configuration for saved templates
     */
    protected function getTemplateConfig(string $template): array
    {
        $configs = [
            'monthly_summary' => [
                'name' => 'Resumen Mensual Completo',
                'report_type' => 'documents',
                'date_from' => now()->startOfMonth(),
                'date_to' => now(),
                'columns' => ['title', 'status_id', 'department_id', 'user_id', 'priority', 'created_at', 'completed_at', 'due_date'],
                'group_by' => ['status_id', 'department_id'],
                'order_by' => [['field' => 'created_at', 'direction' => 'desc']]
            ],
            'weekly_productivity' => [
                'name' => 'Productividad Semanal',
                'report_type' => 'documents',
                'date_from' => now()->startOfWeek(),
                'date_to' => now(),
                'columns' => ['user_id', 'status_id', 'created_at', 'completed_at'],
                'group_by' => ['user_id'],
                'order_by' => [['field' => 'user_id', 'direction' => 'asc']]
            ],
            'quarterly_analysis' => [
                'name' => 'Análisis Trimestral',
                'report_type' => 'documents',
                'date_from' => now()->startOfQuarter(),
                'date_to' => now(),
                'columns' => ['department_id', 'status_id', 'priority', 'created_at', 'completed_at'],
                'group_by' => ['department_id', 'status_id'],
                'order_by' => [['field' => 'department_id', 'direction' => 'asc']]
            ],
            'department_comparison' => [
                'name' => 'Comparación entre Departamentos',
                'report_type' => 'departments',
                'date_from' => now()->subMonth(),
                'date_to' => now(),
                'columns' => ['name', 'is_active'],
                'order_by' => [['field' => 'name', 'direction' => 'asc']]
            ],
            'user_performance' => [
                'name' => 'Rendimiento de Usuarios',
                'report_type' => 'users',
                'date_from' => now()->subMonth(),
                'date_to' => now(),
                'columns' => ['name', 'email', 'department_id', 'role', 'last_login_at'],
                'group_by' => ['department_id'],
                'order_by' => [['field' => 'name', 'direction' => 'asc']]
            ]
        ];
        
        return $configs[$template] ?? [];
    }
}