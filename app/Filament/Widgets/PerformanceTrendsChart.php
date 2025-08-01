<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceTrendsChart extends ChartWidget
{
    protected static ?string $heading = 'Tendencias de Rendimiento';
    
    protected static ?string $description = 'Análisis de rendimiento del sistema en los últimos 30 días';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '400px';
    
    public ?string $filter = 'processing_time';
    
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $cacheKey = 'performance_trends_' . $this->filter . '_' . auth()->id();
        
        return Cache::remember($cacheKey, 600, function () {
            return match ($this->filter) {
                'processing_time' => $this->getProcessingTimeData(),
                'throughput' => $this->getThroughputData(),
                'efficiency' => $this->getEfficiencyData(),
                'user_productivity' => $this->getUserProductivityData(),
                'department_comparison' => $this->getDepartmentComparisonData(),
                default => $this->getProcessingTimeData(),
            };
        });
    }

    protected function getType(): string
    {
        return match ($this->filter) {
            'processing_time' => 'line',
            'throughput' => 'bar',
            'efficiency' => 'line',
            'user_productivity' => 'bar',
            'department_comparison' => 'doughnut',
            default => 'line',
        };
    }

    protected function getFilters(): ?array
    {
        return [
            'processing_time' => 'Tiempo de Procesamiento',
            'throughput' => 'Productividad Diaria',
            'efficiency' => 'Eficiencia del Sistema',
            'user_productivity' => 'Productividad por Usuario',
            'department_comparison' => 'Comparación por Departamento',
        ];
    }

    /**
     * Get processing time trend data
     */
    private function getProcessingTimeData(): array
    {
        $data = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $avgProcessingTime = Document::whereHas('status', function ($query) {
                $query->where('name', 'Completado');
            })
            ->whereDate('updated_at', $date)
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->value('avg_days');
            
            $data[] = $avgProcessingTime ? round($avgProcessingTime, 1) : 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Tiempo Promedio (días)',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get throughput trend data
     */
    private function getThroughputData(): array
    {
        $createdData = [];
        $completedData = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $created = Document::whereDate('created_at', $date)->count();
            $completed = Document::whereHas('status', function ($query) {
                $query->where('name', 'Completado');
            })
            ->whereDate('updated_at', $date)
            ->count();
            
            $createdData[] = $created;
            $completedData[] = $completed;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos Creados',
                    'data' => $createdData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
                [
                    'label' => 'Documentos Completados',
                    'data' => $completedData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get efficiency trend data
     */
    private function getEfficiencyData(): array
    {
        $data = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $created = Document::whereDate('created_at', $date)->count();
            $completed = Document::whereHas('status', function ($query) {
                $query->where('name', 'Completado');
            })
            ->whereDate('updated_at', $date)
            ->count();
            
            $efficiency = $created > 0 ? round(($completed / $created) * 100, 1) : 0;
            $data[] = $efficiency;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Eficiencia (%)',
                    'data' => $data,
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get user productivity data
     */
    private function getUserProductivityData(): array
    {
        $users = User::where('is_active', true)
            ->withCount(['documents' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            }])
            ->orderBy('documents_count', 'desc')
            ->take(10)
            ->get();
        
        $labels = $users->pluck('name')->toArray();
        $data = $users->pluck('documents_count')->toArray();
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos Creados (30 días)',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(14, 165, 233, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(139, 69, 19, 0.8)',
                        'rgba(75, 85, 99, 0.8)',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get department comparison data
     */
    private function getDepartmentComparisonData(): array
    {
        $departments = DB::table('departments')
            ->leftJoin('documents', 'departments.id', '=', 'documents.department_id')
            ->select('departments.name', DB::raw('COUNT(documents.id) as document_count'))
            ->where('documents.created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('document_count', 'desc')
            ->get();
        
        $labels = $departments->pluck('name')->toArray();
        $data = $departments->pluck('document_count')->toArray();
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos por Departamento',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(14, 165, 233, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Get chart options
     */
    protected function getOptions(): array
    {
        return match ($this->filter) {
            'processing_time' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => 'Días',
                        ],
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Fecha',
                        ],
                    ],
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false,
                ],
            ],
            'throughput' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => 'Cantidad de Documentos',
                        ],
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Fecha',
                        ],
                    ],
                ],
            ],
            'efficiency' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'title' => [
                            'display' => true,
                            'text' => 'Porcentaje (%)',
                        ],
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Fecha',
                        ],
                    ],
                ],
            ],
            'user_productivity' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => 'Documentos Creados',
                        ],
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Usuario',
                        ],
                    ],
                ],
            ],
            'department_comparison' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'right',
                    ],
                ],
                'maintainAspectRatio' => false,
            ],
            default => [],
        };
    }

    /**
     * Check if user can view this widget
     */
    public static function canView(): bool
    {
        return auth()->user()->can('view_performance_trends') || 
               auth()->user()->hasRole(['admin', 'manager']);
    }
}