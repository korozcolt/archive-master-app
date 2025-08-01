<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\Branch;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class CategoryDepartmentWidget extends ChartWidget
{
    protected static ?string $heading = 'Documentos por Categoría';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getData(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Obtener documentos por categoría
        $categories = Category::where('company_id', $companyId)
            ->withCount('documents')
            ->orderBy('documents_count', 'desc')
            ->limit(10)
            ->get();
            
        $labels = [];
        $data = [];
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
            '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'
        ];
        
        foreach ($categories as $index => $category) {
            $labels[] = $category->name;
            $data[] = $category->documents_count;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Documentos',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
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
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.label + ": " + context.parsed + " documentos";
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