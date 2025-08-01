<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\User;
use App\Models\Department;
use App\Services\ReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceMetricsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static bool $isLazy = true;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    public function getDisplayName(): string
    {
        return 'Métricas de Rendimiento';
    }

    protected function getStats(): array
    {
        $reportService = app(ReportService::class);
        
        // Cache metrics for 5 minutes to improve performance
        $cacheKey = 'performance_metrics_' . auth()->id();
        
        return Cache::remember($cacheKey, 300, function () use ($reportService) {
            $now = Carbon::now();
            $lastMonth = $now->copy()->subMonth();
            $lastWeek = $now->copy()->subWeek();
            $yesterday = $now->copy()->subDay();
            
            return [
                // Processing Time Metrics
                Stat::make('Tiempo Promedio de Procesamiento', $this->getAverageProcessingTime())
                    ->description('Días promedio para completar documentos')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($this->getProcessingTimeColor())
                    ->chart($this->getProcessingTimeChart()),
                
                // Throughput Metrics
                Stat::make('Documentos Procesados (Hoy)', $this->getDocumentsProcessedToday())
                    ->description($this->getThroughputDescription())
                    ->descriptionIcon('heroicon-m-document-check')
                    ->color('success')
                    ->chart($this->getThroughputChart()),
                
                // Efficiency Metrics
                Stat::make('Eficiencia del Sistema', $this->getSystemEfficiency() . '%')
                    ->description('Documentos completados vs. creados')
                    ->descriptionIcon('heroicon-m-chart-bar')
                    ->color($this->getEfficiencyColor())
                    ->chart($this->getEfficiencyChart()),
                
                // User Productivity
                Stat::make('Productividad por Usuario', $this->getAverageUserProductivity())
                    ->description('Documentos promedio por usuario activo')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('info')
                    ->chart($this->getUserProductivityChart()),
                
                // Department Performance
                Stat::make('Departamento Más Eficiente', $this->getTopPerformingDepartment())
                    ->description('Basado en tiempo de procesamiento')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('warning'),
                
                // System Load
                Stat::make('Carga del Sistema', $this->getSystemLoad())
                    ->description('Documentos pendientes vs. capacidad')
                    ->descriptionIcon('heroicon-m-cpu-chip')
                    ->color($this->getSystemLoadColor())
                    ->chart($this->getSystemLoadChart()),
            ];
        });
    }

    /**
     * Get average processing time in days
     */
    private function getAverageProcessingTime(): string
    {
        $completedDocs = Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->where('created_at', '>=', Carbon::now()->subDays(30))
        ->get();

        if ($completedDocs->isEmpty()) {
            return '0';
        }

        $totalDays = $completedDocs->sum(function ($doc) {
            return $doc->created_at->diffInDays($doc->updated_at);
        });

        $average = round($totalDays / $completedDocs->count(), 1);
        return $average . ' días';
    }

    /**
     * Get processing time color based on performance
     */
    private function getProcessingTimeColor(): string
    {
        $avgDays = $this->getAverageProcessingTimeNumeric();
        
        if ($avgDays <= 2) return 'success';
        if ($avgDays <= 5) return 'warning';
        return 'danger';
    }

    /**
     * Get numeric average processing time
     */
    private function getAverageProcessingTimeNumeric(): float
    {
        $completedDocs = Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->where('created_at', '>=', Carbon::now()->subDays(30))
        ->get();

        if ($completedDocs->isEmpty()) {
            return 0;
        }

        $totalDays = $completedDocs->sum(function ($doc) {
            return $doc->created_at->diffInDays($doc->updated_at);
        });

        return round($totalDays / $completedDocs->count(), 1);
    }

    /**
     * Get processing time chart data
     */
    private function getProcessingTimeChart(): array
    {
        return Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->where('created_at', '>=', Carbon::now()->subDays(7))
        ->select(DB::raw('DATE(updated_at) as date'), DB::raw('AVG(DATEDIFF(updated_at, created_at)) as avg_days'))
        ->groupBy('date')
        ->orderBy('date')
        ->pluck('avg_days')
        ->map(fn($value) => round($value, 1))
        ->toArray();
    }

    /**
     * Get documents processed today
     */
    private function getDocumentsProcessedToday(): int
    {
        return Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->whereDate('updated_at', Carbon::today())
        ->count();
    }

    /**
     * Get throughput description
     */
    private function getThroughputDescription(): string
    {
        $yesterday = Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->whereDate('updated_at', Carbon::yesterday())
        ->count();
        
        $today = $this->getDocumentsProcessedToday();
        $change = $today - $yesterday;
        
        if ($change > 0) {
            return "+{$change} vs. ayer";
        } elseif ($change < 0) {
            return "{$change} vs. ayer";
        }
        
        return "Sin cambios vs. ayer";
    }

    /**
     * Get throughput chart data
     */
    private function getThroughputChart(): array
    {
        return Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->where('updated_at', '>=', Carbon::now()->subDays(7))
        ->select(DB::raw('DATE(updated_at) as date'), DB::raw('COUNT(*) as total'))
        ->groupBy('date')
        ->orderBy('date')
        ->pluck('total')
        ->toArray();
    }

    /**
     * Get system efficiency percentage
     */
    private function getSystemEfficiency(): int
    {
        $totalCreated = Document::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $totalCompleted = Document::whereHas('status', function ($query) {
            $query->where('name', 'Completado');
        })
        ->where('created_at', '>=', Carbon::now()->subDays(30))
        ->count();

        if ($totalCreated === 0) {
            return 0;
        }

        return round(($totalCompleted / $totalCreated) * 100);
    }

    /**
     * Get efficiency color
     */
    private function getEfficiencyColor(): string
    {
        $efficiency = $this->getSystemEfficiency();
        
        if ($efficiency >= 80) return 'success';
        if ($efficiency >= 60) return 'warning';
        return 'danger';
    }

    /**
     * Get efficiency chart data
     */
    private function getEfficiencyChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $created = Document::whereDate('created_at', $date)->count();
            $completed = Document::whereHas('status', function ($query) {
                $query->where('name', 'Completado');
            })
            ->whereDate('updated_at', $date)
            ->count();
            
            $efficiency = $created > 0 ? round(($completed / $created) * 100) : 0;
            $data[] = $efficiency;
        }
        
        return $data;
    }

    /**
     * Get average user productivity
     */
    private function getAverageUserProductivity(): string
    {
        $activeUsers = User::where('is_active', true)->count();
        $totalDocuments = Document::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        
        if ($activeUsers === 0) {
            return '0';
        }
        
        $average = round($totalDocuments / $activeUsers, 1);
        return $average . ' docs/usuario';
    }

    /**
     * Get user productivity chart data
     */
    private function getUserProductivityChart(): array
    {
        return User::where('is_active', true)
            ->withCount(['documents' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            }])
            ->orderBy('documents_count', 'desc')
            ->take(7)
            ->pluck('documents_count')
            ->toArray();
    }

    /**
     * Get top performing department
     */
    private function getTopPerformingDepartment(): string
    {
        $departmentPerformance = Department::withAvg(['documents' => function ($query) {
            $query->whereHas('status', function ($q) {
                $q->where('name', 'Completado');
            })
            ->where('created_at', '>=', Carbon::now()->subDays(30));
        }], DB::raw('DATEDIFF(updated_at, created_at)'))
        ->having('documents_avg_datediff_updated_at_created_at', '>', 0)
        ->orderBy('documents_avg_datediff_updated_at_created_at')
        ->first();

        return $departmentPerformance ? $departmentPerformance->name : 'N/A';
    }

    /**
     * Get system load indicator
     */
    private function getSystemLoad(): string
    {
        $pendingDocs = Document::whereHas('status', function ($query) {
            $query->whereIn('name', ['Pendiente', 'En Proceso']);
        })->count();
        
        $capacity = 100; // Assumed system capacity
        $loadPercentage = min(round(($pendingDocs / $capacity) * 100), 100);
        
        return $loadPercentage . '%';
    }

    /**
     * Get system load color
     */
    private function getSystemLoadColor(): string
    {
        $load = (int) str_replace('%', '', $this->getSystemLoad());
        
        if ($load <= 50) return 'success';
        if ($load <= 80) return 'warning';
        return 'danger';
    }

    /**
     * Get system load chart data
     */
    private function getSystemLoadChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $pending = Document::whereHas('status', function ($query) {
                $query->whereIn('name', ['Pendiente', 'En Proceso']);
            })
            ->whereDate('created_at', '<=', $date)
            ->whereDate('updated_at', '>=', $date)
            ->count();
            
            $capacity = 100;
            $loadPercentage = min(round(($pending / $capacity) * 100), 100);
            $data[] = $loadPercentage;
        }
        
        return $data;
    }

    /**
     * Check if user can view this widget
     */
    public static function canView(): bool
    {
        return auth()->user()->can('view_performance_metrics') || 
               auth()->user()->hasRole(['admin', 'manager']);
    }
}