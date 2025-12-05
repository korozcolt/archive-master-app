<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected function getStats(): array
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        
        return [
            Stat::make('Notificaciones Sin Leer', $this->getUnreadNotificationsCount())
                ->description('Tus notificaciones pendientes')
                ->descriptionIcon('heroicon-m-bell')
                ->color('warning')
                ->chart($this->getNotificationsTrend()),
                
            Stat::make('Documentos Vencidos', $this->getOverdueDocumentsCount($companyId))
                ->description('Documentos fuera de fecha límite')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart($this->getOverdueTrend($companyId)),
                
            Stat::make('Actualizaciones Recientes', $this->getRecentUpdatesCount($companyId))
                ->description('Documentos actualizados hoy')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success')
                ->chart($this->getUpdatesTrend($companyId)),
                
            Stat::make('Asignaciones Pendientes', $this->getPendingAssignmentsCount($user->id))
                ->description('Documentos asignados a ti')
                ->descriptionIcon('heroicon-m-user')
                ->color('info')
                ->chart($this->getAssignmentsTrend($user->id)),
        ];
    }
    
    private function getUnreadNotificationsCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }
    
    private function getOverdueDocumentsCount(int $companyId): int
    {
        return Document::where('company_id', $companyId)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
    }
    
    private function getRecentUpdatesCount(int $companyId): int
    {
        return Document::where('company_id', $companyId)
            ->whereDate('updated_at', today())
            ->count();
    }
    
    private function getPendingAssignmentsCount(int $userId): int
    {
        return Document::where('assigned_to', $userId)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->count();
    }
    
    private function getNotificationsTrend(): array
    {
        $userId = Auth::id();
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = DB::table('notifications')
                ->where('notifiable_id', $userId)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    private function getOverdueTrend(int $companyId): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Document::where('company_id', $companyId)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $date)
                ->whereHas('status', function ($query) {
                    $query->where('is_final', false);
                })
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    private function getUpdatesTrend(int $companyId): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Document::where('company_id', $companyId)
                ->whereDate('updated_at', $date)
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    private function getAssignmentsTrend(int $userId): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Document::where('assigned_to', $userId)
                ->whereDate('created_at', '<=', $date)
                ->whereHas('status', function ($query) {
                    $query->where('is_final', false);
                })
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
}
