<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceMetricsService
{
    /**
     * Get comprehensive performance metrics
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subMonth();
        $dateTo = $filters['date_to'] ?? now();
        $departmentId = $filters['department_id'] ?? null;
        
        return [
            'overview' => $this->getOverviewMetrics($dateFrom, $dateTo, $departmentId),
            'productivity' => $this->getProductivityMetrics($dateFrom, $dateTo, $departmentId),
            'efficiency' => $this->getEfficiencyMetrics($dateFrom, $dateTo, $departmentId),
            'quality' => $this->getQualityMetrics($dateFrom, $dateTo, $departmentId),
            'trends' => $this->getTrendMetrics($dateFrom, $dateTo, $departmentId),
            'department_comparison' => $this->getDepartmentComparison($dateFrom, $dateTo),
            'user_performance' => $this->getUserPerformanceMetrics($dateFrom, $dateTo, $departmentId)
        ];
    }
    
    /**
     * Get overview metrics
     */
    public function getOverviewMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = Document::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        $totalDocuments = $query->count();
        $completedDocuments = $query->whereNotNull('completed_at')->count();
        $pendingDocuments = $query->whereNull('completed_at')->count();
        $overdueDocuments = $query->where('due_date', '<', now())
                                 ->whereNull('completed_at')
                                 ->count();
        
        return [
            'total_documents' => $totalDocuments,
            'completed_documents' => $completedDocuments,
            'pending_documents' => $pendingDocuments,
            'overdue_documents' => $overdueDocuments,
            'completion_rate' => $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 2) : 0,
            'overdue_rate' => $totalDocuments > 0 ? round(($overdueDocuments / $totalDocuments) * 100, 2) : 0
        ];
    }
    
    /**
     * Get productivity metrics
     */
    public function getProductivityMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = Document::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        $days = $dateFrom->diffInDays($dateTo) + 1;
        $totalDocuments = $query->count();
        $dailyAverage = $days > 0 ? round($totalDocuments / $days, 2) : 0;
        
        // Documents by day of week
        $byDayOfWeek = $query->selectRaw('DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count')
                            ->groupBy('day_of_week')
                            ->pluck('count', 'day_of_week')
                            ->toArray();
        
        // Documents by hour
        $byHour = $query->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                       ->groupBy('hour')
                       ->pluck('count', 'hour')
                       ->toArray();
        
        return [
            'daily_average' => $dailyAverage,
            'total_documents' => $totalDocuments,
            'peak_day' => $this->getPeakDay($byDayOfWeek),
            'peak_hour' => $this->getPeakHour($byHour),
            'documents_by_day_of_week' => $byDayOfWeek,
            'documents_by_hour' => $byHour
        ];
    }
    
    /**
     * Get efficiency metrics
     */
    public function getEfficiencyMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = Document::whereBetween('created_at', [$dateFrom, $dateTo])
                        ->whereNotNull('completed_at');
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        // Average processing time in hours
        $avgProcessingTime = $query->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
                                  ->value('avg_hours') ?? 0;
        
        // Processing time by priority
        $processingByPriority = $query->selectRaw('priority, AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
                                     ->groupBy('priority')
                                     ->pluck('avg_hours', 'priority')
                                     ->toArray();
        
        // SLA compliance
        $slaCompliant = $query->whereRaw('completed_at <= due_date')->count();
        $totalCompleted = $query->count();
        $slaComplianceRate = $totalCompleted > 0 ? round(($slaCompliant / $totalCompleted) * 100, 2) : 0;
        
        return [
            'avg_processing_time_hours' => round($avgProcessingTime, 2),
            'processing_by_priority' => $processingByPriority,
            'sla_compliance_rate' => $slaComplianceRate,
            'sla_compliant_documents' => $slaCompliant,
            'total_completed_documents' => $totalCompleted
        ];
    }
    
    /**
     * Get quality metrics
     */
    public function getQualityMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = Document::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        // Documents requiring revision (assuming status indicates this)
        $revisionsRequired = $query->whereHas('status', function($q) {
            $q->where('name->es', 'like', '%revisión%')
              ->orWhere('name->es', 'like', '%corrección%');
        })->count();
        
        $totalDocuments = $query->count();
        $qualityRate = $totalDocuments > 0 ? round((($totalDocuments - $revisionsRequired) / $totalDocuments) * 100, 2) : 0;
        
        // Error rate by category
        $errorsByCategory = $query->whereHas('status', function($q) {
                                    $q->where('name->es', 'like', '%error%')
                                      ->orWhere('name->es', 'like', '%rechazado%');
                                  })
                                  ->with('category')
                                  ->get()
                                  ->groupBy('category.name')
                                  ->map->count()
                                  ->toArray();
        
        return [
            'quality_rate' => $qualityRate,
            'revisions_required' => $revisionsRequired,
            'total_documents' => $totalDocuments,
            'errors_by_category' => $errorsByCategory
        ];
    }
    
    /**
     * Get trend metrics
     */
    public function getTrendMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = Document::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        // Daily document creation trend
        $dailyTrend = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                           ->groupBy('date')
                           ->orderBy('date')
                           ->pluck('count', 'date')
                           ->toArray();
        
        // Weekly completion trend
        $weeklyCompletionTrend = $query->whereNotNull('completed_at')
                                      ->selectRaw('YEARWEEK(completed_at) as week, COUNT(*) as count')
                                      ->groupBy('week')
                                      ->orderBy('week')
                                      ->pluck('count', 'week')
                                      ->toArray();
        
        return [
            'daily_creation_trend' => $dailyTrend,
            'weekly_completion_trend' => $weeklyCompletionTrend,
            'trend_direction' => $this->calculateTrendDirection($dailyTrend)
        ];
    }
    
    /**
     * Get department comparison metrics
     */
    public function getDepartmentComparison(Carbon $dateFrom, Carbon $dateTo): array
    {
        $departments = Department::with(['documents' => function($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])->get();
        
        $comparison = [];
        
        foreach ($departments as $department) {
            $totalDocs = $department->documents->count();
            $completedDocs = $department->documents->whereNotNull('completed_at')->count();
            $avgProcessingTime = $department->documents
                                           ->whereNotNull('completed_at')
                                           ->avg(function($doc) {
                                               return $doc->created_at->diffInHours($doc->completed_at);
                                           }) ?? 0;
            
            $comparison[$department->name] = [
                'total_documents' => $totalDocs,
                'completed_documents' => $completedDocs,
                'completion_rate' => $totalDocs > 0 ? round(($completedDocs / $totalDocs) * 100, 2) : 0,
                'avg_processing_time' => round($avgProcessingTime, 2)
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get user performance metrics
     */
    public function getUserPerformanceMetrics(Carbon $dateFrom, Carbon $dateTo, ?int $departmentId = null): array
    {
        $query = User::with(['documents' => function($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }]);
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        $users = $query->get();
        $userMetrics = [];
        
        foreach ($users as $user) {
            $totalDocs = $user->documents->count();
            $completedDocs = $user->documents->whereNotNull('completed_at')->count();
            $avgProcessingTime = $user->documents
                                    ->whereNotNull('completed_at')
                                    ->avg(function($doc) {
                                        return $doc->created_at->diffInHours($doc->completed_at);
                                    }) ?? 0;
            
            if ($totalDocs > 0) {
                $userMetrics[$user->name] = [
                    'total_documents' => $totalDocs,
                    'completed_documents' => $completedDocs,
                    'completion_rate' => round(($completedDocs / $totalDocs) * 100, 2),
                    'avg_processing_time' => round($avgProcessingTime, 2),
                    'productivity_score' => $this->calculateProductivityScore($totalDocs, $completedDocs, $avgProcessingTime)
                ];
            }
        }
        
        // Sort by productivity score
        uasort($userMetrics, function($a, $b) {
            return $b['productivity_score'] <=> $a['productivity_score'];
        });
        
        return $userMetrics;
    }
    
    /**
     * Calculate productivity score
     */
    protected function calculateProductivityScore(int $total, int $completed, float $avgTime): float
    {
        if ($total === 0) return 0;
        
        $completionRate = ($completed / $total) * 100;
        $timeEfficiency = $avgTime > 0 ? min(100, (24 / $avgTime) * 10) : 100; // Normalize to 100
        
        return round(($completionRate * 0.7) + ($timeEfficiency * 0.3), 2);
    }
    
    /**
     * Get peak day of week
     */
    protected function getPeakDay(array $byDayOfWeek): string
    {
        if (empty($byDayOfWeek)) return 'N/A';
        
        $maxDay = array_keys($byDayOfWeek, max($byDayOfWeek))[0];
        
        $days = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miércoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sábado'
        ];
        
        return $days[$maxDay] ?? 'N/A';
    }
    
    /**
     * Get peak hour
     */
    protected function getPeakHour(array $byHour): string
    {
        if (empty($byHour)) return 'N/A';
        
        $maxHour = array_keys($byHour, max($byHour))[0];
        
        return sprintf('%02d:00', $maxHour);
    }
    
    /**
     * Calculate trend direction
     */
    protected function calculateTrendDirection(array $dailyTrend): string
    {
        if (count($dailyTrend) < 2) return 'stable';
        
        $values = array_values($dailyTrend);
        $firstHalf = array_slice($values, 0, ceil(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $difference = (($secondAvg - $firstAvg) / $firstAvg) * 100;
        
        if ($difference > 5) return 'increasing';
        if ($difference < -5) return 'decreasing';
        return 'stable';
    }
    
    /**
     * Get KPI dashboard data
     */
    public function getKPIDashboard(array $filters = []): array
    {
        $metrics = $this->getPerformanceMetrics($filters);
        
        return [
            'kpis' => [
                [
                    'name' => 'Tasa de Completado',
                    'value' => $metrics['overview']['completion_rate'],
                    'unit' => '%',
                    'trend' => $metrics['trends']['trend_direction'],
                    'target' => 85,
                    'status' => $metrics['overview']['completion_rate'] >= 85 ? 'good' : 'warning'
                ],
                [
                    'name' => 'Tiempo Promedio de Procesamiento',
                    'value' => $metrics['efficiency']['avg_processing_time_hours'],
                    'unit' => 'horas',
                    'trend' => 'stable',
                    'target' => 24,
                    'status' => $metrics['efficiency']['avg_processing_time_hours'] <= 24 ? 'good' : 'warning'
                ],
                [
                    'name' => 'Cumplimiento SLA',
                    'value' => $metrics['efficiency']['sla_compliance_rate'],
                    'unit' => '%',
                    'trend' => 'stable',
                    'target' => 90,
                    'status' => $metrics['efficiency']['sla_compliance_rate'] >= 90 ? 'good' : 'warning'
                ],
                [
                    'name' => 'Tasa de Calidad',
                    'value' => $metrics['quality']['quality_rate'],
                    'unit' => '%',
                    'trend' => 'stable',
                    'target' => 95,
                    'status' => $metrics['quality']['quality_rate'] >= 95 ? 'good' : 'warning'
                ]
            ],
            'charts' => [
                'daily_trend' => $metrics['trends']['daily_creation_trend'],
                'department_comparison' => $metrics['department_comparison'],
                'user_performance' => array_slice($metrics['user_performance'], 0, 10, true)
            ]
        ];
    }
}