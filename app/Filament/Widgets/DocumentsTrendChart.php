<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class DocumentsTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Tendencia de Documentos (Últimos 30 días)';
    protected static ?string $description = 'Evolución diaria de la creación de documentos';
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = true;
    
    protected function getData(): array
    {
        $cacheKey = 'documents_trend_chart_' . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, 3600, function () {
            $data = [];
            $labels = [];
            $createdData = [];
            $completedData = [];
            $pendingData = [];
            
            // Get data for the last 30 days
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('d/m');
                
                // Documents created on this date
                $created = Document::whereDate('created_at', $date)->count();
                $createdData[] = $created;
                
                // Documents completed on this date
                $completed = Document::whereDate('completed_at', $date)->count();
                $completedData[] = $completed;
                
                // Documents pending at end of this date
                $pending = Document::where('created_at', '<=', $date->endOfDay())
                    ->where(function ($query) use ($date) {
                        $query->whereNull('completed_at')
                            ->orWhere('completed_at', '>', $date->endOfDay());
                    })
                    ->count();
                $pendingData[] = $pending;
            }
            
            return [
                'datasets' => [
                    [
                        'label' => 'Documentos Creados',
                        'data' => $createdData,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Documentos Completados',
                        'data' => $completedData,
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Documentos Pendientes',
                        'data' => $pendingData,
                        'borderColor' => '#f59e0b',
                        'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
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
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
            'elements' => [
                'point' => [
                    'radius' => 3,
                    'hoverRadius' => 6,
                ],
            ],
        ];
    }
    
    public static function canView(): bool
    {
        return Auth::user()->can('view_reports') || Auth::user()->hasRole(['admin', 'manager']);
    }
}