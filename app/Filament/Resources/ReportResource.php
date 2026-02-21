<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\ReportResource\Pages;
use App\Models\Department;
use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $modelLabel = 'Reporte';

    protected static ?string $pluralModelLabel = 'Reportes';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: [
            'admin',
            'branch_admin',
            'office_manager',
            'archive_manager',
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración del Reporte')
                    ->description('Selecciona el tipo de reporte y los filtros a aplicar')
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

                Section::make('Filtros')
                    ->description('Aplica filtros para personalizar el reporte')
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('report_type')
                    ->label('Tipo de Reporte')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('filters')
                    ->label('Filtros Aplicados')
                    ->limit(50),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('generate')
                    ->label('Generar')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->form([
                        Section::make('Configuración del Reporte')
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

                        Section::make('Filtros')
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

                            // Generate report data based on type
                            $reportData = match ($data['report_type']) {
                                'documents-by-status' => $reportService->documentsByStatus($filters),
                                'sla-compliance' => $reportService->slaComplianceReport($filters),
                                'user-activity' => $reportService->userActivityReport($filters),
                                'documents-by-department' => $reportService->documentsByDepartment($filters),
                                default => collect()
                            };

                            // Generate output based on format
                            if ($data['output_format'] === 'excel') {
                                return $reportService->generateExcel($data['report_type'], $reportData);
                            } else {
                                return $reportService->generatePDF($data['report_type'], $reportData, $filters);
                            }

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al generar reporte')
                                ->body('Ocurrió un error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return null;
                        }
                    })
                    ->modalHeading('Generar Reporte')
                    ->modalSubmitActionLabel('Generar y Descargar')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No hay reportes disponibles')
            ->emptyStateDescription('Utiliza el botón "Generar" para crear un nuevo reporte.')
            ->emptyStateIcon('heroicon-o-chart-bar');
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
            'index' => Pages\ListReports::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
