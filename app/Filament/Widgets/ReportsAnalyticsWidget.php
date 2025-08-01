<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Department;
use App\Models\User;
use App\Services\ReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ReportsAnalyticsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = true;
    
    protected function getStats(): array
    {
        // Cache the expensive calculations for 5 minutes
        $cacheKey = 'reports_analytics_widget_' . now()->format('Y-m-d-H-i');
        
        return Cache::remember($cacheKey, 300, function () {
            $reportService = app(ReportService::class);
            
            // Get current month data
            $currentMonthFilters = [
                'date_from' => now()->startOfMonth(),
                'date_to' => now()->endOfMonth(),
            ];
            
            // Get previous month data for comparison
            $previousMonthFilters = [
                'date_from' => now()->subMonth()->startOfMonth(),
                'date_to' => now()->subMonth()->endOfMonth(),
            ];
            
            // Current month metrics
            $currentMonthDocs = Document::whereBetween('created_at', [
                $currentMonthFilters['date_from'],
                $currentMonthFilters['date_to']
            ])->count();
            
            // Previous month metrics
            $previousMonthDocs = Document::whereBetween('created_at', [
                $previousMonthFilters['date_from'],
                $previousMonthFilters['date_to']
            ])->count();
            
            // Calculate percentage change
            $docsChange = $previousMonthDocs > 0 
                ? round((($currentMonthDocs - $previousMonthDocs) / $previousMonthDocs) * 100, 1)
                : ($currentMonthDocs > 0 ? 100 : 0);
            
            // SLA Compliance
            $slaData = $reportService->slaComplianceReport($currentMonthFilters);
            $slaCompliance = $slaData['summary']['compliance_rate'] ?? 0;
            
            $previousSlaData = $reportService->slaComplianceReport($previousMonthFilters);
            $previousSlaCompliance = $previousSlaData['summary']['compliance_rate'] ?? 0;
            
            $slaChange = $previousSlaCompliance > 0 
                ? round($slaCompliance - $previousSlaCompliance, 1)
                : 0;
            
            // Active departments
            $activeDepartments = Department::whereHas('documents', function ($query) use ($currentMonthFilters) {
                $query->whereBetween('created_at', [
                    $currentMonthFilters['date_from'],
                    $currentMonthFilters['date_to']
                ]);
            })->count();
            
            $totalDepartments = Department::count();
            $departmentActivity = $totalDepartments > 0 
                ? round(($activeDepartments / $totalDepartments) * 100, 1)
                : 0;
            
            // Average processing time
            $avgProcessingTime = Document::whereBetween('created_at', [
                $currentMonthFilters['date_from'],
                $currentMonthFilters['date_to']
            ])
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(DATEDIFF(completed_at, created_at)) as avg_days')
            ->value('avg_days');
            
            $avgProcessingTime = round($avgProcessingTime ?? 0, 1);
            
            // Previous month average processing time
            $previousAvgProcessingTime = Document::whereBetween('created_at', [
                $previousMonthFilters['date_from'],
                $previousMonthFilters['date_to']
            ])
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(DATEDIFF(completed_at, created_at)) as avg_days')
            ->value('avg_days');
            
            $previousAvgProcessingTime = round($previousAvgProcessingTime ?? 0, 1);
            $processingTimeChange = $previousAvgProcessingTime > 0 
                ? round($avgProcessingTime - $previousAvgProcessingTime, 1)
                : 0;
            
            return [
                Stat::make('Documentos Este Mes', number_format($currentMonthDocs))
                    ->description($docsChange >= 0 ? "+{$docsChange}% vs mes anterior" : "{$docsChange}% vs mes anterior")
                    ->descriptionIcon($docsChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->color($docsChange >= 0 ? 'success' : 'danger')
                    ->chart($this->getDocumentsTrendChart()),
                    
                Stat::make('Cumplimiento SLA', "{$slaCompliance}%")
                    ->description($slaChange >= 0 ? "+{$slaChange}% vs mes anterior" : "{$slaChange}% vs mes anterior")
                    ->descriptionIcon($slaChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->color($slaCompliance >= 90 ? 'success' : ($slaCompliance >= 75 ? 'warning' : 'danger'))
                    ->chart($this->getSlaComplianceChart()),
                    
                Stat::make('Departamentos Activos', "{$activeDepartments}/{$totalDepartments}")
                    ->description("{$departmentActivity}% de departamentos con actividad")
                    ->descriptionIcon('heroicon-m-building-office-2')
                    ->color($departmentActivity >= 80 ? 'success' : ($departmentActivity >= 60 ? 'warning' : 'danger')),
                    
                Stat::make('Tiempo Promedio', "{$avgProcessingTime} días")
                    ->description($processingTimeChange <= 0 ? 
                        ($processingTimeChange == 0 ? "Sin cambios" : "{$processingTimeChange} días menos") : 
                        "+{$processingTimeChange} días más")
                    ->descriptionIcon($processingTimeChange <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                    ->color($processingTimeChange <= 0 ? 'success' : 'danger')
                    ->chart($this->getProcessingTimeChart()),
            ];
        });
    }
    
    private function getDocumentsTrendChart(): array
    {
        // Get last 7 days document creation data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Document::whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    private function getSlaComplianceChart(): array
    {
        // Get last 7 days SLA compliance data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $total = Document::whereDate('created_at', $date)->count();
            $compliant = Document::whereDate('created_at', $date)
                ->where(function ($query) {
                    $query->whereNull('sla_due_date')
                        ->orWhere('completed_at', '<=', 'sla_due_date')
                        ->orWhereNull('completed_at');
                })
                ->count();
            
            $compliance = $total > 0 ? round(($compliant / $total) * 100) : 100;
            $data[] = $compliance;
        }
        
        return $data;
    }
    
    private function getProcessingTimeChart(): array
    {
        // Get last 7 days average processing time
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $avgTime = Document::whereDate('created_at', $date)
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(DATEDIFF(completed_at, created_at)) as avg_days')
                ->value('avg_days');
            
            $data[] = round($avgTime ?? 0, 1);
        }
        
        return $data;
    }
    
    public static function canView(): bool
    {
        return auth()->user()->can('view_reports') || auth()->user()->hasRole(['admin', 'manager']);
    }
}