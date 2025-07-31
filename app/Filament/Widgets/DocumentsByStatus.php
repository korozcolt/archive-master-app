<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Status;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class DocumentsByStatus extends ChartWidget
{
    protected static ?string $heading = 'Documentos por Estado';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '300px';
    
    public ?string $filter = 'month';
    
    protected function getData(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Obtener todos los estados de la empresa
        $statuses = Status::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get();
            
        $labels = [];
        $data = [];
        $colors = [];
        
        foreach ($statuses as $status) {
            // Manejar nombres traducibles
            $statusName = $status->name;
            if (is_array($statusName)) {
                $statusName = $status->getTranslation('name', app()->getLocale());
            }
            $labels[] = $statusName;
            
            // Contar documentos por estado según el filtro
            $query = Document::where('company_id', $companyId)
                ->where('status_id', $status->id);
                
            // Aplicar filtro de fecha
            switch ($this->filter) {
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
                case 'quarter':
                    $query->where('created_at', '>=', now()->subQuarter());
                    break;
                case 'year':
                    $query->where('created_at', '>=', now()->subYear());
                    break;
                // 'all' no aplica filtro adicional
            }
            
            $count = $query->count();
            $data[] = $count;
            
            // Asignar colores basados en el color del estado
            $colors[] = $this->getStatusColor($status->color ?? 'gray');
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
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
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
    
    protected function getFilters(): ?array
    {
        return [
            'week' => 'Última semana',
            'month' => 'Último mes',
            'quarter' => 'Último trimestre',
            'year' => 'Último año',
            'all' => 'Todos los tiempos',
        ];
    }
    
    /**
     * Convertir color del estado a color de gráfico
     */
    private function getStatusColor(string $color): string
    {
        return match ($color) {
            'red' => 'rgb(239, 68, 68)',
            'yellow' => 'rgb(245, 158, 11)',
            'green' => 'rgb(34, 197, 94)',
            'blue' => 'rgb(59, 130, 246)',
            'purple' => 'rgb(147, 51, 234)',
            'pink' => 'rgb(236, 72, 153)',
            'indigo' => 'rgb(99, 102, 241)',
            'orange' => 'rgb(249, 115, 22)',
            'teal' => 'rgb(20, 184, 166)',
            'cyan' => 'rgb(6, 182, 212)',
            default => 'rgb(107, 114, 128)',
        };
    }
    
    /**
     * Determinar si el widget debe ser visible
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}