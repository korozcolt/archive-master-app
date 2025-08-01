<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Flujo de Documentos (Últimos 30 días)';
    
    protected static ?int $sort = 6;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getData(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Obtener transiciones de workflow de los últimos 30 días
        $workflowData = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as transitions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        // Crear array de los últimos 30 días
        $labels = [];
        $data = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d/m');
            
            $dayData = $workflowData->firstWhere('date', $date);
            $data[] = $dayData ? $dayData->transitions : 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Transiciones de Workflow',
                    'data' => $data,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": " + context.parsed.y + " transiciones";
                        }'
                    ]
                ]
            ],
        ];
    }
    
    public static function canView(): bool
    {
        return Auth::check();
    }
}