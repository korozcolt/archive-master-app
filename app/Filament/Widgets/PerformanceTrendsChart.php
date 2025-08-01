<?php

namespace App\Filament\Widgets;

use App\Services\PerformanceMetricsService;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Auth;

class PerformanceTrendsChart extends ChartWidget
{
    protected static ?string $heading = 'Tendencias de Rendimiento';
    
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    public ?string $filter = 'documents';
    
    public ?string $period = '30';

    protected function getData(): array
    {
        $performanceService = app(PerformanceMetricsService::class);
        $days = (int) $this->period;
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();
        
        $trends = $performanceService->getTrendMetrics($dateFrom, $dateTo);
        
        switch ($this->filter) {
            case 'documents':
                return $this->getDocumentTrends($trends);
            case 'productivity':
                return $this->getProductivityTrends($trends);
            case 'efficiency':
                return $this->getEfficiencyTrends($trends);
            case 'quality':
                return $this->getQualityTrends($trends);
            default:
                return $this->getDocumentTrends($trends);
        }
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'documents' => 'Documentos Procesados',
            'productivity' => 'Productividad',
            'efficiency' => 'Eficiencia',
            'quality' => 'Calidad',
        ];
    }
    
    protected function getFormSchema(): array
    {
        return [
            Select::make('period')
                ->label('Período')
                ->options([
                    '7' => 'Últimos 7 días',
                    '30' => 'Últimos 30 días',
                    '90' => 'Últimos 90 días',
                    '365' => 'Último año',
                ])
                ->default('30')
                ->reactive(),
        ];
    }

    private function getDocumentTrends($trends): array
    {
        $labels = array_keys($trends['daily_documents'] ?? []);
        $data = array_values($trends['daily_documents'] ?? []);
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos Procesados',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getProductivityTrends($trends): array
    {
        $labels = array_keys($trends['daily_productivity'] ?? []);
        $data = array_values($trends['daily_productivity'] ?? []);
        
        return [
            'datasets' => [
                [
                    'label' => 'Productividad (%)',
                    'data' => $data,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getEfficiencyTrends($trends): array
    {
        $labels = array_keys($trends['daily_efficiency'] ?? []);
        $data = array_values($trends['daily_efficiency'] ?? []);
        
        return [
            'datasets' => [
                [
                    'label' => 'Eficiencia (%)',
                    'data' => $data,
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getQualityTrends($trends): array
    {
        $labels = array_keys($trends['daily_quality'] ?? []);
        $data = array_values($trends['daily_quality'] ?? []);
        
        return [
            'datasets' => [
                [
                    'label' => 'Calidad (%)',
                    'data' => $data,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public static function canView(): bool
    {
        return Auth::user()->can('view_performance_metrics');
    }
}