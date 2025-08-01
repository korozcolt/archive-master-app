<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class SlaComplianceChart extends ChartWidget
{
    protected static ?string $heading = 'Cumplimiento de SLA (Últimas 4 semanas)';
    protected static ?string $description = 'Evolución semanal del cumplimiento de acuerdos de nivel de servicio';
    protected static ?int $sort = 4;
    protected static ?string $pollingInterval = '60s';
    protected static bool $isLazy = true;
    
    protected function getData(): array
    {
        $cacheKey = 'sla_compliance_chart_' . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, 3600, function () {
            $labels = [];
            $complianceData = [];
            $overdueData = [];
            $totalData = [];
            
            // Get data for the last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();
                
                $labels[] = 'Semana ' . $weekStart->format('d/m');
                
                // Total documents with SLA in this week
                $totalWithSla = Document::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->whereNotNull('sla_due_date')
                    ->count();
                
                // Documents that met SLA (completed before or on due date)
                $compliantDocs = Document::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->whereNotNull('sla_due_date')
                    ->whereNotNull('completed_at')
                    ->whereRaw('completed_at <= sla_due_date')
                    ->count();
                
                // Documents that missed SLA (completed after due date or still pending past due date)
                $overdueDocs = Document::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->whereNotNull('sla_due_date')
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            // Completed after due date
                            $q->whereNotNull('completed_at')
                              ->whereRaw('completed_at > sla_due_date');
                        })
                        ->orWhere(function ($q) {
                            // Still pending and past due date
                            $q->whereNull('completed_at')
                              ->where('sla_due_date', '<', now());
                        });
                    })
                    ->count();
                
                $totalData[] = $totalWithSla;
                $complianceData[] = $totalWithSla > 0 ? round(($compliantDocs / $totalWithSla) * 100, 1) : 0;
                $overdueData[] = $totalWithSla > 0 ? round(($overdueDocs / $totalWithSla) * 100, 1) : 0;
            }
            
            return [
                'datasets' => [
                    [
                        'label' => 'Cumplimiento SLA (%)',
                        'data' => $complianceData,
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'Incumplimiento SLA (%)',
                        'data' => $overdueData,
                        'borderColor' => '#ef4444',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'Total Documentos con SLA',
                        'data' => $totalData,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                        'type' => 'bar',
                        'yAxisID' => 'y1',
                        'order' => 1,
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
                    'callbacks' => [
                        'label' => 'function(context) {
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.dataset.yAxisID === "y1") {
                                label += context.parsed.y + " documentos";
                            } else {
                                label += context.parsed.y + "%";
                            }
                            return label;
                        }'
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'max' => 100,
                    'title' => [
                        'display' => true,
                        'text' => 'Porcentaje (%)',
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return value + "%"; }'
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Cantidad de Documentos',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Período',
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
                    'radius' => 4,
                    'hoverRadius' => 7,
                ],
            ],
        ];
    }
    
    public static function canView(): bool
    {
        return Auth::user()->can('view_reports') || Auth::user()->hasRole(['admin', 'manager']);
    }
}