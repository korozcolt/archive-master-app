<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\Department;
use App\Models\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DocumentsExport;

class ReportService
{
    /**
     * Generate documents by status report
     */
    public function documentsByStatus(array $filters = []): Collection
    {
        $query = Document::with(['status', 'category', 'user', 'department'])
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
        $query = Document::with(['status', 'category', 'user', 'department'])
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
                }
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
            'title' => $this->getReportTitle($reportType)
        ]);

        return $pdf->download("{$reportType}_" . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Generate Excel export
     */
    public function generateExcel(string $reportType, Collection $data): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new DocumentsExport($data, $reportType),
            "{$reportType}_" . now()->format('Y-m-d_H-i-s') . '.xlsx'
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
                ->whereHas('status', fn($q) => $q->where('name', 'Completed'))->count(),
            'overdue_documents' => Document::where('due_date', '<', now())
                ->whereHas('status', fn($q) => $q->where('name', '!=', 'Completed'))->count(),
            'avg_processing_time' => $this->getAverageProcessingTime($dateFrom, $dateTo),
            'documents_by_status' => $this->getDocumentsByStatusChart($dateFrom, $dateTo),
            'monthly_trends' => $this->getMonthlyTrends($dateFrom, $dateTo)
        ];
    }

    /**
     * Get average processing time in days
     */
    private function getAverageProcessingTime(Carbon $dateFrom, Carbon $dateTo): float
    {
        $completedDocs = Document::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('status', fn($q) => $q->where('name', 'Completed'))
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
                    'total' => $item->total
                ];
            })
            ->toArray();
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
            default => 'Reporte del Sistema'
        };
    }
}