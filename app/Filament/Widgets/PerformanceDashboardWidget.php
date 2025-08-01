<?php

namespace App\Filament\Widgets;

use App\Services\PerformanceMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PerformanceDashboardWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static bool $isLazy = false;
    
    protected int | string | array $columnSpan = 'full';
    
    public function getStats(): array
    {
        $performanceService = app(PerformanceMetricsService::class);
        $dateFrom = Carbon::now()->subDays(30);
        $dateTo = Carbon::now();
        
        $overview = $performanceService->getOverviewMetrics($dateFrom, $dateTo);
        $productivity = $performanceService->getProductivityMetrics($dateFrom, $dateTo);
        $efficiency = $performanceService->getEfficiencyMetrics($dateFrom, $dateTo);
        $quality = $performanceService->getQualityMetrics($dateFrom, $dateTo);
        
        return [
            Stat::make('Documentos Procesados', $overview['total_documents'])
                ->description('Últimos 30 días')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart($this->getDocumentTrend()),
            
            Stat::make('Tiempo Promedio de Procesamiento', $this->formatTime($overview['avg_processing_time']))
                ->description($this->getProcessingTimeChange($overview['processing_time_change']))
                ->descriptionIcon($overview['processing_time_change'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($overview['processing_time_change'] >= 0 ? 'danger' : 'success'),
            
            Stat::make('Productividad', number_format($productivity['productivity_score'], 1) . '%')
                ->description($this->getProductivityChange($productivity['productivity_change']))
                ->descriptionIcon($productivity['productivity_change'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($productivity['productivity_change'] >= 0 ? 'success' : 'danger'),
            
            Stat::make('Eficiencia', number_format($efficiency['efficiency_score'], 1) . '%')
                ->description('Documentos completados vs iniciados')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
            
            Stat::make('Calidad', number_format($quality['quality_score'], 1) . '%')
                ->description($quality['rejected_documents'] . ' documentos rechazados')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($quality['quality_score'] >= 90 ? 'success' : ($quality['quality_score'] >= 70 ? 'warning' : 'danger')),
            
            Stat::make('Usuarios Activos', $overview['active_users'])
                ->description('En los últimos 30 días')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
        ];
    }
    
    private function getDocumentTrend(): array
    {
        $performanceService = app(PerformanceMetricsService::class);
        $trends = $performanceService->getTrendMetrics(
            Carbon::now()->subDays(7),
            Carbon::now()
        );
        
        return array_values($trends['daily_documents'] ?? []);
    }
    
    private function formatTime($seconds): string
    {
        if ($seconds < 60) {
            return number_format($seconds, 1) . 's';
        } elseif ($seconds < 3600) {
            return number_format($seconds / 60, 1) . 'm';
        } else {
            return number_format($seconds / 3600, 1) . 'h';
        }
    }
    
    private function getProcessingTimeChange($change): string
    {
        $percentage = abs($change);
        $direction = $change >= 0 ? 'aumento' : 'reducción';
        return number_format($percentage, 1) . '% ' . $direction;
    }
    
    private function getProductivityChange($change): string
    {
        $percentage = abs($change);
        $direction = $change >= 0 ? 'mejora' : 'disminución';
        return number_format($percentage, 1) . '% ' . $direction;
    }
    
    public static function canView(): bool
    {
        return Auth::user()->can('view_performance_metrics');
    }
}