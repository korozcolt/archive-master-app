<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Services\WorkflowEngine;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class WorkflowMetricsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected function getStats(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? 1;
        
        $workflowEngine = new WorkflowEngine();
        $metrics = $workflowEngine->getWorkflowMetrics($companyId, 30);
        $overdueDocuments = $workflowEngine->getOverdueDocuments();
        
        // Filtrar documentos vencidos por empresa del usuario
        $companyOverdueDocuments = collect($overdueDocuments)->filter(function ($item) use ($companyId) {
            return $item['document']->company_id === $companyId;
        });
        
        $pendingDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
            
        $completedThisMonth = Document::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereMonth('completed_at', now()->month)
            ->count();
        
        return [
            Stat::make('Documentos Pendientes', $pendingDocuments)
                ->description('Documentos en proceso')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Documentos Vencidos', $companyOverdueDocuments->count())
                ->description('Documentos que excedieron SLA')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
                
            Stat::make('Completados este Mes', $completedThisMonth)
                ->description('Documentos finalizados')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Tiempo Promedio', number_format($metrics['avg_processing_time'], 1) . 'h')
                ->description('Tiempo de procesamiento')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
                
            Stat::make('Cumplimiento SLA', number_format($metrics['sla_compliance_rate'], 1) . '%')
                ->description('Tasa de cumplimiento')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($metrics['sla_compliance_rate'] >= 80 ? 'success' : 'warning'),
                
            Stat::make('Transiciones Totales', $metrics['total_transitions'])
                ->description('Últimos 30 días')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
    
    protected function getColumns(): int
    {
        return 3;
    }
}