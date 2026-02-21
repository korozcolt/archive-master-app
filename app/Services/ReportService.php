<?php

namespace App\Services;

use App\Exports\DocumentsExport;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{
    /**
     * Generate documents by status report
     */
    public function documentsByStatus(array $filters = []): Collection
    {
        $query = Document::with(['status', 'category', 'creator', 'department'])
            ->select('documents.*', DB::raw('count(*) as total'))
            ->groupBy('status_id');

        // Apply date filters
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply department filter
        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->get();
    }

    /**
     * Generate SLA compliance report
     */
    public function slaComplianceReport(array $filters = []): Collection
    {
        $query = Document::with(['status', 'category', 'creator', 'department'])
            ->select(
                'documents.*',
                DB::raw('CASE 
                    WHEN due_date IS NULL THEN "No SLA"
                    WHEN due_date > NOW() THEN "On Time"
                    WHEN due_date <= NOW() AND status_id != (SELECT id FROM statuses WHERE name = "Completed" LIMIT 1) THEN "Overdue"
                    ELSE "Completed"
                END as sla_status')
            );

        // Apply filters
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->get();
    }

    /**
     * Generate user activity report
     */
    public function userActivityReport(array $filters = []): Collection
    {
        $query = User::with(['documents', 'department'])
            ->withCount([
                'documents',
                'documents as completed_documents_count' => function ($query) {
                    $query->whereHas('status', function ($q) {
                        $q->where('name', 'Completed');
                    });
                },
                'documents as pending_documents_count' => function ($query) {
                    $query->whereHas('status', function ($q) {
                        $q->where('name', '!=', 'Completed');
                    });
                },
            ]);

        // Apply date filters for document creation
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $query->whereHas('documents', function ($q) use ($filters) {
                if (isset($filters['date_from'])) {
                    $q->where('created_at', '>=', $filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $q->where('created_at', '<=', $filters['date_to']);
                }
            });
        }

        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->get();
    }

    /**
     * Generate documents by department report
     */
    public function documentsByDepartment(array $filters = []): Collection
    {
        $query = Department::with(['documents.status', 'documents.category'])
            ->withCount('documents')
            ->having('documents_count', '>', 0);

        // Apply date filters
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $query->whereHas('documents', function ($q) use ($filters) {
                if (isset($filters['date_from'])) {
                    $q->where('created_at', '>=', $filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $q->where('created_at', '<=', $filters['date_to']);
                }
            });
        }

        return $query->get();
    }

    /**
     * Generate PDF report
     */
    public function generatePDF(string $reportType, array $data, array $filters = []): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView("reports.{$reportType}", [
            'data' => $data,
            'filters' => $filters,
            'generated_at' => now(),
            'title' => $this->getReportTitle($reportType),
        ]);

        return $pdf->download("{$reportType}_".now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Generate Excel export
     */
    public function generateExcel(string $reportType, Collection $data): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new DocumentsExport($data, $reportType),
            "{$reportType}_".now()->format('Y-m-d_H-i-s').'.xlsx'
        );
    }

    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subMonth();
        $dateTo = $filters['date_to'] ?? now();

        return [
            'total_documents' => Document::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'completed_documents' => Document::whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereHas('status', fn ($q) => $q->where('name', 'Completed'))->count(),
            'overdue_documents' => Document::where('due_date', '<', now())
                ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Completed'))->count(),
            'avg_processing_time' => $this->getAverageProcessingTime($dateFrom, $dateTo),
            'documents_by_status' => $this->getDocumentsByStatusChart($dateFrom, $dateTo),
            'monthly_trends' => $this->getMonthlyTrends($dateFrom, $dateTo),
        ];
    }

    /**
     * Get average processing time in days
     */
    private function getAverageProcessingTime(Carbon $dateFrom, Carbon $dateTo): float
    {
        $completedDocs = Document::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('status', fn ($q) => $q->where('name', 'Completed'))
            ->whereNotNull('updated_at')
            ->get();

        if ($completedDocs->isEmpty()) {
            return 0;
        }

        $totalDays = $completedDocs->sum(function ($doc) {
            return $doc->created_at->diffInDays($doc->updated_at);
        });

        return round($totalDays / $completedDocs->count(), 2);
    }

    /**
     * Get documents by status for charts
     */
    private function getDocumentsByStatusChart(Carbon $dateFrom, Carbon $dateTo): array
    {
        return Document::whereBetween('created_at', [$dateFrom, $dateTo])
            ->join('statuses', 'documents.status_id', '=', 'statuses.id')
            ->select('statuses.name', DB::raw('count(*) as total'))
            ->groupBy('statuses.name')
            ->pluck('total', 'name')
            ->toArray();
    }

    /**
     * Get monthly trends
     */
    private function getMonthlyTrends(Carbon $dateFrom, Carbon $dateTo): array
    {
        return Document::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('count(*) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month)->format('Y-m'),
                    'total' => $item->total,
                ];
            })
            ->toArray();
    }

    /**
     * Generate custom report based on dynamic configuration
     */
    public function generateCustomReport(array $config): Collection
    {
        $reportType = $config['report_type'] ?? 'documents';
        $filters = $config['filters'] ?? [];
        $columns = $config['columns'] ?? ['*'];
        $groupBy = $config['group_by'] ?? [];
        $orderBy = $config['order_by'] ?? [];
        $dateFrom = $config['date_from'] ?? null;
        $dateTo = $config['date_to'] ?? null;

        // Build base query based on report type
        $query = $this->buildBaseQuery($reportType);

        // Apply date range filters
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        // Apply custom filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        // Apply column selection
        if ($columns !== ['*']) {
            $query->select($columns);
        }

        // Apply grouping
        foreach ($groupBy as $group) {
            $query->groupBy($group);
        }

        // Apply ordering
        foreach ($orderBy as $order) {
            $query->orderBy($order['field'], $order['direction'] ?? 'asc');
        }

        return $query->get();
    }

    /**
     * Build base query for different report types
     */
    private function buildBaseQuery(string $reportType)
    {
        return match ($reportType) {
            'documents' => Document::with(['status', 'category', 'user', 'department']),
            'users' => User::with(['documents', 'department']),
            'departments' => Department::with(['documents', 'users']),
            default => Document::with(['status', 'category', 'user', 'department'])
        };
    }

    /**
     * Apply individual filter to query
     */
    private function applyFilter($query, array $filter): void
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;

        switch ($operator) {
            case 'equals':
                $query->where($field, '=', $value);
                break;
            case 'not_equals':
                $query->where($field, '!=', $value);
                break;
            case 'contains':
                $query->where($field, 'LIKE', "%{$value}%");
                break;
            case 'starts_with':
                $query->where($field, 'LIKE', "{$value}%");
                break;
            case 'ends_with':
                $query->where($field, 'LIKE', "%{$value}");
                break;
            case 'greater_than':
                $query->where($field, '>', $value);
                break;
            case 'less_than':
                $query->where($field, '<', $value);
                break;
            case 'greater_equal':
                $query->where($field, '>=', $value);
                break;
            case 'less_equal':
                $query->where($field, '<=', $value);
                break;
            case 'in':
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                }
                break;
            case 'not_in':
                if (is_array($value)) {
                    $query->whereNotIn($field, $value);
                }
                break;
            case 'null':
                $query->whereNull($field);
                break;
            case 'not_null':
                $query->whereNotNull($field);
                break;
        }
    }

    /**
     * Get aggregated data for custom reports
     */
    public function getCustomReportAggregates(array $config): array
    {
        $reportType = $config['report_type'] ?? 'documents';
        $filters = $config['filters'] ?? [];
        $dateFrom = $config['date_from'] ?? null;
        $dateTo = $config['date_to'] ?? null;

        $query = $this->buildBaseQuery($reportType);

        // Apply date range filters
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        // Apply custom filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        $aggregates = [];

        // Calculate common aggregates based on report type
        switch ($reportType) {
            case 'documents':
                $aggregates = [
                    'total_documents' => $query->count(),
                    'avg_processing_time' => $this->calculateAverageProcessingTime($query),
                    'documents_by_status' => $this->getDocumentStatusDistribution($query),
                    'documents_by_department' => $this->getDocumentDepartmentDistribution($query),
                ];
                break;
            case 'users':
                $aggregates = [
                    'total_users' => $query->count(),
                    'active_users' => $query->where('is_active', true)->count(),
                    'users_by_department' => $this->getUserDepartmentDistribution($query),
                ];
                break;
            case 'departments':
                $aggregates = [
                    'total_departments' => $query->count(),
                    'departments_with_documents' => $query->has('documents')->count(),
                ];
                break;
        }

        return $aggregates;
    }

    /**
     * Get document status distribution
     */
    private function getDocumentStatusDistribution($query): array
    {
        return $query->join('statuses', 'documents.status_id', '=', 'statuses.id')
            ->select('statuses.name', DB::raw('count(*) as total'))
            ->groupBy('statuses.name')
            ->pluck('total', 'name')
            ->toArray();
    }

    /**
     * Get document department distribution
     */
    private function getDocumentDepartmentDistribution($query): array
    {
        return $query->join('departments', 'documents.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('count(*) as total'))
            ->groupBy('departments.name')
            ->pluck('total', 'name')
            ->toArray();
    }

    /**
     * Get user department distribution
     */
    private function getUserDepartmentDistribution($query): array
    {
        return $query->join('departments', 'users.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('count(*) as total'))
            ->groupBy('departments.name')
            ->pluck('total', 'name')
            ->toArray();
    }

    /**
     * Calculate average processing time for documents
     */
    private function calculateAverageProcessingTime($query): float
    {
        $completedDocs = $query->whereHas('status', function ($q) {
            $q->where('name', 'Completado');
        })->get();

        if ($completedDocs->isEmpty()) {
            return 0;
        }

        $totalDays = $completedDocs->sum(function ($doc) {
            return $doc->created_at->diffInDays($doc->updated_at);
        });

        return round($totalDays / $completedDocs->count(), 2);
    }

    /**
     * Get report title by type
     */
    private function getReportTitle(string $reportType): string
    {
        return match ($reportType) {
            'documents-by-status' => 'Reporte de Documentos por Estado',
            'sla-compliance' => 'Reporte de Cumplimiento SLA',
            'user-activity' => 'Reporte de Actividad por Usuario',
            'documents-by-department' => 'Reporte de Documentos por Departamento',
            'custom' => 'Reporte Personalizado',
            default => 'Reporte del Sistema'
        };
    }
}
