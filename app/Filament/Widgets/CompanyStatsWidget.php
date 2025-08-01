<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CompanyStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        // Total de empresas (solo para super admin)
        $totalCompanies = $user->hasRole('super_admin') ? Company::count() : 1;
        
        // Usuarios en la empresa
        $totalUsers = User::where('company_id', $companyId)->count();
        
        // Documentos activos
        $activeDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
            
        // Documentos completados este mes
        $completedThisMonth = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', true);
            })
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();
        
        $stats = [
            Stat::make('Usuarios Activos', $totalUsers)
                ->description('Total de usuarios en la empresa')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
                
            Stat::make('Documentos Activos', $activeDocuments)
                ->description('Documentos en proceso')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
                
            Stat::make('Completados este mes', $completedThisMonth)
                ->description('Documentos finalizados')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
        
        // Solo mostrar estadística de empresas para super admin
        if ($user->hasRole('super_admin')) {
            array_unshift($stats, 
                Stat::make('Total Empresas', $totalCompanies)
                    ->description('Empresas registradas')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('primary')
            );
        }
        
        return $stats;
    }
    
    public static function canView(): bool
    {
        return Auth::check();
    }
}