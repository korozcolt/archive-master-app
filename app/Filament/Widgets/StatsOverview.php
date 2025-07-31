<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Status;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Obtener estadísticas básicas
        $totalDocuments = Document::where('company_id', $companyId)->count();
        $pendingDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
        $completedDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', true);
            })
            ->count();
        $myDocuments = Document::where('company_id', $companyId)
            ->where('assigned_to', $user->id)
            ->count();
            
        // Calcular tendencias (comparar con el mes anterior)
        $lastMonthTotal = Document::where('company_id', $companyId)
            ->whereBetween('created_at', [now()->subMonth(2), now()->subMonth()])
            ->count();
        $thisMonthTotal = Document::where('company_id', $companyId)
            ->whereBetween('created_at', [now()->subMonth(), now()])
            ->count();
        $trend = $lastMonthTotal > 0 ? (($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100 : 0;
        
        return [
            Stat::make('Total de Documentos', $totalDocuments)
                ->description($trend >= 0 ? "+{$trend}% desde el mes pasado" : "{$trend}% desde el mes pasado")
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'danger')
                ->chart($this->getDocumentChart($companyId)),
                
            Stat::make('Documentos Pendientes', $pendingDocuments)
                ->description('En proceso')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Documentos Completados', $completedDocuments)
                ->description('Finalizados')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Mis Documentos', $myDocuments)
                ->description('Asignados a mí')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),
        ];
    }
    
    /**
     * Obtener datos para el gráfico de documentos
     */
    private function getDocumentChart(int $companyId): array
    {
        $data = [];
        
        // Obtener datos de los últimos 7 días
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = Document::where('company_id', $companyId)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    /**
     * Determinar si el widget debe ser visible
     */
    public static function canView(): bool
    {
        return Auth::check();
    }
}