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
            Stat::make('Unread Notifications', $this->getUnreadNotificationsCount())
                ->description('Your unread notifications')
                ->descriptionIcon('heroicon-m-bell')
                ->color('warning')
                ->chart($this->getNotificationsTrend()),
                
            Stat::make('Overdue Documents', $this->getOverdueDocumentsCount($companyId))
                ->description('Documents past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart($this->getOverdueTrend($companyId)),
                
            Stat::make('Recent Updates', $this->getRecentUpdatesCount($companyId))
                ->description('Documents updated today')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success')
                ->chart($this->getUpdatesTrend($companyId)),
                
            Stat::make('Pending Assignments', $this->getPendingAssignmentsCount($user->id))
                ->description('Documents assigned to you')
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
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
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
            ->whereNotIn('status', ['completed', 'cancelled'])
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
                ->whereNotNull('due_date')
                ->where('due_date', '<', $date)
                ->whereNotIn('status', ['completed', 'cancelled'])
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
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            $data[] = $count;
        }
        
        return $data;
    }
}
