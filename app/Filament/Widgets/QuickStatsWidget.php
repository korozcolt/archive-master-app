<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class QuickStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected function getStats(): array
    {
        $user = Auth::user();
        
        $myPending = Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
            
        $myCompletedToday = Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', true);
            })
            ->whereDate('updated_at', today())
            ->count();
            
        $companyTotal = Document::where('company_id', $user->company_id)
            ->count();
            
        $companyPending = Document::where('company_id', $user->company_id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
        
        return [
            Stat::make('Mis Pendientes', $myPending)
                ->description('Documentos asignados a ti')
                ->descriptionIcon('heroicon-m-user')
                ->color($myPending > 10 ? 'warning' : 'primary')
                ->chart($this->getMyPendingTrend()),
                
            Stat::make('Completados Hoy', $myCompletedToday)
                ->description('Documentos que completaste hoy')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Total Empresa', $companyTotal)
                ->description('Todos los documentos')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),
                
            Stat::make('Pendientes Empresa', $companyPending)
                ->description('En proceso en toda la empresa')
                ->descriptionIcon('heroicon-m-clock')
                ->color($companyPending > 50 ? 'danger' : 'warning'),
        ];
    }
    
    private function getMyPendingTrend(): array
    {
        $user = Auth::user();
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Document::where('assigned_to', $user->id)
                ->whereHas('status', function ($query) {
                    $query->where('is_final', false);
                })
                ->whereDate('created_at', '<=', $date)
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
}
