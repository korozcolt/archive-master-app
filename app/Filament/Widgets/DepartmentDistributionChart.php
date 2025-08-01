<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Document;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class DepartmentDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribución de Documentos por Departamento';
    protected static ?string $description = 'Volumen de documentos procesados por cada departamento este mes';
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = '60s';
    protected static bool $isLazy = true;
    
    protected function getData(): array
    {
        $cacheKey = 'department_distribution_chart_' . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, 3600, function () {
            // Get current month data
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            
            $departments = Department::withCount([
                'documents' => function ($query) use ($startOfMonth, $endOfMonth) {
                    $query->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                }
            ])
            ->having('documents_count', '>', 0)
            ->orderByDesc('documents_count')
            ->limit(10) // Top 10 departments
            ->get();
            
            $labels = $departments->pluck('name')->toArray();
            $data = $departments->pluck('documents_count')->toArray();
            
            // Generate colors for each department
            $colors = [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
            ];
            
            // Ensure we have enough colors
            while (count($colors) < count($data)) {
                $colors = array_merge($colors, $colors);
            }
            
            $backgroundColors = array_slice($colors, 0, count($data));
            
            return [
                'datasets' => [
                    [
                        'label' => 'Documentos',
                        'data' => $data,
                        'backgroundColor' => $backgroundColors,
                        'borderColor' => array_map(function($color) {
                            return $color;
                        }, $backgroundColors),
                        'borderWidth' => 2,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
    
    protected function getType(): string
    {
        return 'doughnut';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ": " + value + " documentos (" + percentage + "%)";
                        }'
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '50%',
            'elements' => [
                'arc' => [
                    'borderWidth' => 2,
                ],
            ],
        ];
    }
    
    public static function canView(): bool
    {
        return auth()->user()->can('view_reports') || auth()->user()->hasRole(['admin', 'manager']);
    }
}