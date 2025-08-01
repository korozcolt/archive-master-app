<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomReportResource\Pages;
use App\Services\ReportBuilderService;
use App\Models\Department;
use App\Models\Status;
use App\Models\User;
use App\Models\CustomReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CustomReportResource extends Resource
{
    protected static ?string $model = CustomReport::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationLabel = 'Constructor de Reportes';
    
    protected static ?string $modelLabel = 'Reporte Personalizado';
    
    protected static ?string $pluralModelLabel = 'Reportes Personalizados';
    
    protected static ?string $navigationGroup = 'Reportes';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuración del Reporte')
                    ->schema([
                        Forms\Components\TextInput::make('report_name')
                            ->label('Nombre del Reporte')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Reporte de Productividad Mensual'),
                        
                        Forms\Components\Select::make('report_type')
                            ->label('Tipo de Reporte')
                            ->required()
                            ->options([
                                'documents' => 'Documentos',
                                'users' => 'Usuarios',
                                'departments' => 'Departamentos'
                            ])
                            ->default('documents')
                            ->reactive(),
                        
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Fecha Desde')
                            ->default(now()->subMonth())
                            ->maxDate(now()),
                        
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Fecha Hasta')
                            ->default(now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Filtros')
                    ->schema([
                        Forms\Components\Repeater::make('filters')
                            ->label('Filtros Personalizados')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label('Campo')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $reportType = $get('../../report_type');
                                        return static::getFieldsForReportType($reportType);
                                    })
                                    ->reactive(),
                                
                                Forms\Components\Select::make('operator')
                                    ->label('Operador')
                                    ->required()
                                    ->options([
                                        '=' => 'Igual a',
                                        '!=' => 'Diferente de',
                                        '>' => 'Mayor que',
                                        '<' => 'Menor que',
                                        '>=' => 'Mayor o igual que',
                                        '<=' => 'Menor o igual que',
                                        'like' => 'Contiene',
                                        'in' => 'En lista',
                                        'not_in' => 'No en lista',
                                        'between' => 'Entre',
                                        'null' => 'Es nulo',
                                        'not_null' => 'No es nulo'
                                    ]),
                                
                                Forms\Components\TextInput::make('value')
                                    ->label('Valor')
                                    ->required(function (callable $get) {
                                        return !in_array($get('operator'), ['null', 'not_null']);
                                    })
                                    ->placeholder('Valor del filtro')
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->addActionLabel('Agregar Filtro')
                            ->defaultItems(0),
                    ]),
                
                Forms\Components\Section::make('Columnas y Agrupación')
                    ->schema([
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Columnas a Incluir')
                            ->options(function (callable $get) {
                                $reportType = $get('report_type');
                                return static::getColumnsForReportType($reportType);
                            })
                            ->columns(3)
                            ->reactive(),
                        
                        Forms\Components\Select::make('group_by')
                            ->label('Agrupar Por')
                            ->multiple()
                            ->options(function (callable $get) {
                                $reportType = $get('report_type');
                                return static::getGroupByFieldsForReportType($reportType);
                            }),
                        
                        Forms\Components\Repeater::make('order_by')
                            ->label('Ordenar Por')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label('Campo')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $reportType = $get('../../../report_type');
                                        return static::getFieldsForReportType($reportType);
                                    }),
                                
                                Forms\Components\Select::make('direction')
                                    ->label('Dirección')
                                    ->required()
                                    ->options([
                                        'asc' => 'Ascendente',
                                        'desc' => 'Descendente'
                                    ])
                                    ->default('asc')
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->addActionLabel('Agregar Orden')
                            ->defaultItems(0),
                    ]),
                
                Forms\Components\Section::make('Opciones de Exportación')
                    ->schema([
                        Forms\Components\Select::make('export_format')
                            ->label('Formato de Exportación')
                            ->required()
                            ->options([
                                'pdf' => 'PDF',
                                'excel' => 'Excel',
                                'csv' => 'CSV'
                            ])
                            ->default('pdf'),
                        
                        Forms\Components\Select::make('chart_type')
                            ->label('Tipo de Gráfico (opcional)')
                            ->options([
                                'line' => 'Líneas',
                                'bar' => 'Barras',
                                'pie' => 'Circular',
                                'doughnut' => 'Dona'
                            ])
                            ->placeholder('Sin gráfico'),
                        
                        Forms\Components\Toggle::make('include_aggregates')
                            ->label('Incluir Datos Agregados')
                            ->default(true)
                            ->helperText('Incluye totales, promedios y estadísticas resumidas'),
                        
                        Forms\Components\Toggle::make('schedule_report')
                            ->label('Programar Reporte')
                            ->default(false)
                            ->reactive(),
                        
                        Forms\Components\Select::make('schedule_frequency')
                            ->label('Frecuencia')
                            ->options([
                                'daily' => 'Diario',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                                'quarterly' => 'Trimestral'
                            ])
                            ->visible(fn (callable $get) => $get('schedule_report')),
                        
                        Forms\Components\TagsInput::make('email_recipients')
                            ->label('Destinatarios de Email')
                            ->placeholder('email@ejemplo.com')
                            ->visible(fn (callable $get) => $get('schedule_report'))
                            ->helperText('Emails que recibirán el reporte automáticamente')
                    ])
                    ->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        // Since this resource doesn't have a model, we return an empty table
        // The actual functionality is handled in the ListCustomReports page
        return $table
            ->columns([])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomReports::route('/'),
            'create' => Pages\CreateCustomReport::route('/create'),
        ];
    }
    
    /**
     * Get available fields for a report type
     */
    protected static function getFieldsForReportType(?string $reportType): array
    {
        switch ($reportType) {
            case 'documents':
                return [
                    'title' => 'Título',
                    'status_id' => 'Estado',
                    'department_id' => 'Departamento',
                    'user_id' => 'Usuario',
                    'category_id' => 'Categoría',
                    'priority' => 'Prioridad',
                    'created_at' => 'Fecha de Creación',
                    'updated_at' => 'Fecha de Actualización',
                    'completed_at' => 'Fecha de Completado',
                    'due_date' => 'Fecha Límite'
                ];
            case 'users':
                return [
                    'name' => 'Nombre',
                    'email' => 'Email',
                    'department_id' => 'Departamento',
                    'role' => 'Rol',
                    'is_active' => 'Activo',
                    'created_at' => 'Fecha de Creación',
                    'last_login_at' => 'Último Login'
                ];
            case 'departments':
                return [
                    'name' => 'Nombre',
                    'description' => 'Descripción',
                    'is_active' => 'Activo',
                    'created_at' => 'Fecha de Creación'
                ];
            default:
                return [];
        }
    }
    
    /**
     * Get available columns for a report type
     */
    protected static function getColumnsForReportType(?string $reportType): array
    {
        return static::getFieldsForReportType($reportType);
    }
    
    /**
     * Get available group by fields for a report type
     */
    protected static function getGroupByFieldsForReportType(?string $reportType): array
    {
        switch ($reportType) {
            case 'documents':
                return [
                    'status_id' => 'Estado',
                    'department_id' => 'Departamento',
                    'category_id' => 'Categoría',
                    'priority' => 'Prioridad',
                    'user_id' => 'Usuario'
                ];
            case 'users':
                return [
                    'department_id' => 'Departamento',
                    'role' => 'Rol',
                    'is_active' => 'Estado'
                ];
            case 'departments':
                return [
                    'is_active' => 'Estado'
                ];
            default:
                return [];
        }
    }
    
    /**
     * Generate report with the given configuration
     */
    protected static function generateReport(array $data): void
    {
        try {
            $builder = app(ReportBuilderService::class);
            
            // Configure the report builder
            $builder->setReportType($data['report_type'] ?? 'documents');
            
            // Set date range
            if (!empty($data['date_from']) && !empty($data['date_to'])) {
                $builder->setDateRange(
                    Carbon::parse($data['date_from']),
                    Carbon::parse($data['date_to'])
                );
            }
            
            // Add filters
            if (!empty($data['filters'])) {
                foreach ($data['filters'] as $filter) {
                    $builder->addFilter(
                        $filter['field'],
                        $filter['operator'],
                        $filter['value']
                    );
                }
            }
            
            // Set columns
            if (!empty($data['columns'])) {
                $builder->setColumns($data['columns']);
            }
            
            // Set group by
            if (!empty($data['group_by'])) {
                foreach ($data['group_by'] as $group) {
                    $builder->groupBy($group);
                }
            }
            
            // Set order by
            if (!empty($data['order_by'])) {
                foreach ($data['order_by'] as $order) {
                    $builder->orderBy($order['field'], $order['direction']);
                }
            }
            
            // Generate the report
            $filePath = $builder->export($data['export_format'] ?? 'pdf');
            
            // Store file path in session for potential download
            session(['generated_report_path' => $filePath]);
            
            Notification::make()
                ->title('Reporte generado exitosamente')
                ->body('El reporte "' . ($data['report_name'] ?? 'Reporte Personalizado') . '" ha sido generado.')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al generar reporte')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    /**
     * Preview report data
     */
    protected static function previewReport(array $data): void
    {
        try {
            $builder = app(ReportBuilderService::class);
            
            // Configure the report builder (same as generate)
            $builder->setReportType($data['report_type'] ?? 'documents');
            
            if (!empty($data['date_from']) && !empty($data['date_to'])) {
                $builder->setDateRange(
                    Carbon::parse($data['date_from']),
                    Carbon::parse($data['date_to'])
                );
            }
            
            if (!empty($data['filters'])) {
                foreach ($data['filters'] as $filter) {
                    $builder->addFilter(
                        $filter['field'],
                        $filter['operator'],
                        $filter['value']
                    );
                }
            }
            
            // Get preview data (limited to 10 records)
            $previewData = $builder->build()->take(10);
            $aggregates = $builder->getAggregatedData();
            
            Notification::make()
                ->title('Vista previa del reporte')
                ->body('Registros encontrados: ' . $previewData->count() . ' (mostrando primeros 10)')
                ->info()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en vista previa')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}