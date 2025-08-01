<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowHistory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductivityStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        return [
            $this->getDocumentsProcessedToday($companyId),
            $this->getAverageProcessingTime($companyId),
            $this->getProductivityScore($companyId),
            $this->getOverdueDocuments($companyId),
            $this->getWorkflowEfficiency($companyId),
            $this->getUserActivityScore($user),
        ];
    }
    
    private function getDocumentsProcessedToday(int $companyId): Stat
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        
        $todayCount = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->whereDate('created_at', $today)
            ->count();
            
        $yesterdayCount = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->whereDate('created_at', $yesterday)
            ->count();
            
        $change = $yesterdayCount > 0 ? (($todayCount - $yesterdayCount) / $yesterdayCount) * 100 : 0;
        
        return Stat::make('Documentos Procesados Hoy', $todayCount)
            ->description($change >= 0 ? "+{$change}% desde ayer" : "{$change}% desde ayer")
            ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($change >= 0 ? 'success' : 'danger')
            ->chart($this->getLastWeekProcessingChart($companyId));
    }
    
    private function getAverageProcessingTime(int $companyId): Stat
    {
        $avgTime = DB::table('workflow_histories as wh1')
            ->join('workflow_histories as wh2', function ($join) {
                $join->on('wh1.document_id', '=', 'wh2.document_id')
                     ->whereRaw('wh2.created_at > wh1.created_at');
            })
            ->join('documents', 'wh1.document_id', '=', 'documents.id')
            ->where('documents.company_id', $companyId)
            ->whereDate('wh1.created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('AVG((julianday(wh2.created_at) - julianday(wh1.created_at)) * 24) as avg_hours')
            ->value('avg_hours');
            
        $avgTime = round($avgTime ?? 0, 1);
        
        $color = 'primary';
        if ($avgTime <= 24) $color = 'success';
        elseif ($avgTime <= 72) $color = 'warning';
        else $color = 'danger';
        
        return Stat::make('Tiempo Promedio de Procesamiento', "{$avgTime}h")
            ->description('Últimos 30 días')
            ->descriptionIcon('heroicon-m-clock')
            ->color($color);
    }
    
    private function getProductivityScore(int $companyId): Stat
    {
        // Calcular score basado en múltiples factores
        $totalDocuments = Document::where('company_id', $companyId)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(30))
            ->count();
            
        $completedDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', true);
            })
            ->whereDate('updated_at', '>=', Carbon::now()->subDays(30))
            ->count();
            
        $overdueDocuments = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->count();
            
        $completionRate = $totalDocuments > 0 ? ($completedDocuments / $totalDocuments) * 100 : 0;
        $overdueRate = $totalDocuments > 0 ? ($overdueDocuments / $totalDocuments) * 100 : 0;
        
        $score = max(0, min(100, $completionRate - ($overdueRate * 2)));
        $score = round($score, 1);
        
        $color = 'primary';
        if ($score >= 80) $color = 'success';
        elseif ($score >= 60) $color = 'warning';
        else $color = 'danger';
        
        return Stat::make('Score de Productividad', "{$score}%")
            ->description('Basado en completitud y puntualidad')
            ->descriptionIcon('heroicon-m-chart-bar')
            ->color($color);
    }
    
    private function getOverdueDocuments(int $companyId): Stat
    {
        $overdueCount = Document::where('company_id', $companyId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->count();
            
        $color = 'success';
        if ($overdueCount > 0) $color = 'warning';
        if ($overdueCount > 10) $color = 'danger';
        
        return Stat::make('Documentos Vencidos', $overdueCount)
            ->description('Más de 7 días sin procesar')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($color);
    }
    
    private function getWorkflowEfficiency(int $companyId): Stat
    {
        // Calcular eficiencia basada en transiciones exitosas vs rechazos
        $totalTransitions = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->whereDate('created_at', '>=', Carbon::now()->subDays(30))
            ->count();
            
        $rejectedTransitions = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->whereHas('toStatus', function ($query) {
                $query->where('name', 'like', '%rechazado%')
                      ->orWhere('name', 'like', '%rejected%');
            })
            ->whereDate('created_at', '>=', Carbon::now()->subDays(30))
            ->count();
            
        $efficiency = $totalTransitions > 0 ? (($totalTransitions - $rejectedTransitions) / $totalTransitions) * 100 : 100;
        $efficiency = round($efficiency, 1);
        
        $color = 'primary';
        if ($efficiency >= 90) $color = 'success';
        elseif ($efficiency >= 75) $color = 'warning';
        else $color = 'danger';
        
        return Stat::make('Eficiencia de Workflow', "{$efficiency}%")
            ->description('Transiciones exitosas vs rechazos')
            ->descriptionIcon('heroicon-m-arrow-path')
            ->color($color);
    }
    
    private function getUserActivityScore(User $user): Stat
    {
        $userTransitions = WorkflowHistory::where('user_id', $user->id)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
            
        $userDocuments = Document::where('assignee_id', $user->id)
            ->whereDate('updated_at', '>=', Carbon::now()->subDays(7))
            ->count();
            
        $activityScore = $userTransitions + ($userDocuments * 2);
        
        $color = 'primary';
        if ($activityScore >= 20) $color = 'success';
        elseif ($activityScore >= 10) $color = 'warning';
        else $color = 'danger';
        
        return Stat::make('Tu Actividad Semanal', $activityScore)
            ->description('Transiciones y documentos gestionados')
            ->descriptionIcon('heroicon-m-user')
            ->color($color);
    }
    
    private function getLastWeekProcessingChart(int $companyId): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->whereDate('created_at', $date)
                ->count();
                
            $data[] = $count;
        }
        
        return $data;
    }
}