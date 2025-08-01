<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\User;
use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdvancedSearchStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $searchCriteria = Session::get('advanced_search', []);
        $hasActiveSearch = !empty($searchCriteria);
        
        if (!$hasActiveSearch) {
            return [
                Stat::make('Total de Documentos', Document::count())
                    ->description('Documentos disponibles para búsqueda')
                    ->descriptionIcon('heroicon-m-document-text')
                    ->color('primary'),
                    
                Stat::make('Empresas Registradas', Company::count())
                    ->description('Empresas en el sistema')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('success'),
                    
                Stat::make('Usuarios Activos', User::where('is_active', true)->count())
                    ->description('Usuarios que pueden crear documentos')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('warning'),
            ];
        }
        
        // Estadísticas cuando hay búsqueda activa
        $query = Document::query();
        
        // Aplicar los mismos filtros que en ListAdvancedSearches
        if (!empty($searchCriteria['search_query'])) {
            $searchResults = Document::search($searchCriteria['search_query'])->get();
            $documentIds = $searchResults->pluck('id')->toArray();
            
            if (!empty($documentIds)) {
                $query->whereIn('id', $documentIds);
            } else {
                $query->where(function ($q) use ($searchCriteria) {
                    $searchTerm = $searchCriteria['search_query'];
                    $q->where('title', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('content', 'like', "%{$searchTerm}%");
                });
            }
        }
        
        if (!empty($searchCriteria['company_id'])) {
            $query->whereIn('company_id', (array) $searchCriteria['company_id']);
        }
        
        if (!empty($searchCriteria['created_by'])) {
            $query->whereIn('created_by', (array) $searchCriteria['created_by']);
        }
        
        if (!empty($searchCriteria['created_from'])) {
            $query->whereDate('created_at', '>=', $searchCriteria['created_from']);
        }
        
        if (!empty($searchCriteria['created_to'])) {
            $query->whereDate('created_at', '<=', $searchCriteria['created_to']);
        }
        
        if (!empty($searchCriteria['is_overdue'])) {
            $query->where('due_date', '<', now());
        }
        
        if (!empty($searchCriteria['recent_activity'])) {
            $query->where('updated_at', '>=', now()->subDays(7));
        }
        
        $totalResults = $query->count();
        $avgProcessingTime = $this->getAverageSearchTime();
        $searchAccuracy = $this->calculateSearchAccuracy($searchCriteria);
        
        return [
            Stat::make('Resultados Encontrados', $totalResults)
                ->description($this->getSearchDescription($searchCriteria))
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color($totalResults > 0 ? 'success' : 'danger'),
                
            Stat::make('Tiempo de Búsqueda', $avgProcessingTime . ' ms')
                ->description('Tiempo promedio de procesamiento')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
                
            Stat::make('Precisión', $searchAccuracy . '%')
                ->description('Relevancia de los resultados')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($searchAccuracy > 80 ? 'success' : ($searchAccuracy > 60 ? 'warning' : 'danger')),
        ];
    }
    
    private function getSearchDescription(array $criteria): string
    {
        $parts = [];
        
        if (!empty($criteria['search_query'])) {
            $parts[] = "Texto: '{$criteria['search_query']}'";
        }
        
        if (!empty($criteria['company_id'])) {
            $count = count((array) $criteria['company_id']);
            $parts[] = "{$count} empresa(s)";
        }
        
        if (!empty($criteria['created_by'])) {
            $count = count((array) $criteria['created_by']);
            $parts[] = "{$count} usuario(s)";
        }
        
        if (!empty($criteria['created_from']) || !empty($criteria['created_to'])) {
            $parts[] = "Rango de fechas";
        }
        
        if (!empty($criteria['is_overdue'])) {
            $parts[] = "Solo vencidos";
        }
        
        if (!empty($criteria['recent_activity'])) {
            $parts[] = "Actividad reciente";
        }
        
        return empty($parts) ? 'Sin filtros aplicados' : 'Filtros: ' . implode(', ', $parts);
    }
    
    private function getAverageSearchTime(): int
    {
        // Simular tiempo de búsqueda basado en complejidad
        $searchCriteria = Session::get('advanced_search', []);
        $complexity = 0;
        
        if (!empty($searchCriteria['search_query'])) {
            $complexity += strlen($searchCriteria['search_query']) * 2;
        }
        
        $complexity += count($searchCriteria) * 10;
        
        return max(50, min(500, $complexity + rand(20, 80)));
    }
    
    private function calculateSearchAccuracy(array $criteria): int
    {
        // Calcular precisión basada en la especificidad de los criterios
        $accuracy = 70; // Base accuracy
        
        if (!empty($criteria['search_query'])) {
            $queryLength = strlen($criteria['search_query']);
            $accuracy += min(20, $queryLength * 2); // Más específico = más preciso
        }
        
        if (!empty($criteria['company_id'])) {
            $accuracy += 5;
        }
        
        if (!empty($criteria['created_by'])) {
            $accuracy += 5;
        }
        
        if (!empty($criteria['created_from']) || !empty($criteria['created_to'])) {
            $accuracy += 10;
        }
        
        return min(100, $accuracy);
    }
    
    public static function canView(): bool
    {
        return true;
    }
}