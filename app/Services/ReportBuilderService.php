<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\Department;
use App\Models\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportBuilderService
{
    protected array $filters = [];
    protected array $columns = [];
    protected array $groupBy = [];
    protected array $orderBy = [];
    protected string $reportType = 'documents';
    protected ?Carbon $dateFrom = null;
    protected ?Carbon $dateTo = null;

    /**
     * Set the report type
     */
    public function setReportType(string $type): self
    {
        $this->reportType = $type;
        return $this;
    }

    /**
     * Add a filter to the report
     */
    public function addFilter(string $field, string $operator, $value): self
    {
        $this->filters[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }

    /**
     * Set columns to include in the report
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add group by clause
     */
    public function groupBy(string $field): self
    {
        $this->groupBy[] = $field;
        return $this;
    }

    /**
     * Add order by clause
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->orderBy[] = [
            'field' => $field,
            'direction' => $direction
        ];
        return $this;
    }

    /**
     * Set date range for the report
     */
    public function setDateRange(?Carbon $from, ?Carbon $to): self
    {
        $this->dateFrom = $from;
        $this->dateTo = $to;
        return $this;
    }

    /**
     * Build and execute the report query
     */
    public function build(): Collection
    {
        $query = $this->getBaseQuery();
        
        // Apply filters
        foreach ($this->filters as $filter) {
            $this->applyFilter($query, $filter);
        }
        
        // Apply date range
        if ($this->dateFrom && $this->dateTo) {
            $query->whereBetween('created_at', [$this->dateFrom, $this->dateTo]);
        }
        
        // Apply columns selection
        if (!empty($this->columns)) {
            $query->select($this->columns);
        }
        
        // Apply group by
        if (!empty($this->groupBy)) {
            foreach ($this->groupBy as $group) {
                $query->groupBy($group);
            }
        }
        
        // Apply order by
        if (!empty($this->orderBy)) {
            foreach ($this->orderBy as $order) {
                $query->orderBy($order['field'], $order['direction']);
            }
        }
        
        return $query->get();
    }

    /**
     * Get aggregated data for the report
     */
    public function getAggregatedData(): array
    {
        $query = $this->getBaseQuery();
        
        // Apply filters
        foreach ($this->filters as $filter) {
            $this->applyFilter($query, $filter);
        }
        
        // Apply date range
        if ($this->dateFrom && $this->dateTo) {
            $query->whereBetween('created_at', [$this->dateFrom, $this->dateTo]);
        }
        
        switch ($this->reportType) {
            case 'documents':
                return $this->getDocumentAggregates($query);
            case 'users':
                return $this->getUserAggregates($query);
            case 'departments':
                return $this->getDepartmentAggregates($query);
            default:
                return [];
        }
    }

    /**
     * Get chart data for visualization
     */
    public function getChartData(string $chartType = 'line'): array
    {
        $data = $this->build();
        
        switch ($chartType) {
            case 'line':
                return $this->formatLineChartData($data);
            case 'bar':
                return $this->formatBarChartData($data);
            case 'pie':
                return $this->formatPieChartData($data);
            case 'doughnut':
                return $this->formatDoughnutChartData($data);
            default:
                return [];
        }
    }

    /**
     * Export report to specified format
     */
    public function export(string $format = 'pdf'): string
    {
        $data = $this->build();
        $aggregates = $this->getAggregatedData();
        
        switch ($format) {
            case 'pdf':
                return $this->exportToPdf($data, $aggregates);
            case 'excel':
                return $this->exportToExcel($data, $aggregates);
            case 'csv':
                return $this->exportToCsv($data);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Get the base query for the report type
     */
    protected function getBaseQuery()
    {
        switch ($this->reportType) {
            case 'documents':
                return Document::with(['user', 'department', 'status', 'category']);
            case 'users':
                return User::with(['department', 'documents']);
            case 'departments':
                return Department::with(['documents', 'users']);
            default:
                throw new \InvalidArgumentException("Unsupported report type: {$this->reportType}");
        }
    }

    /**
     * Apply a filter to the query
     */
    protected function applyFilter($query, array $filter): void
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];
        
        switch ($operator) {
            case '=':
                $query->where($field, $value);
                break;
            case '!=':
                $query->where($field, '!=', $value);
                break;
            case '>':
                $query->where($field, '>', $value);
                break;
            case '<':
                $query->where($field, '<', $value);
                break;
            case '>=':
                $query->where($field, '>=', $value);
                break;
            case '<=':
                $query->where($field, '<=', $value);
                break;
            case 'like':
                $query->where($field, 'like', "%{$value}%");
                break;
            case 'in':
                $query->whereIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'not_in':
                $query->whereNotIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($field, $value);
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
     * Get document aggregates
     */
    protected function getDocumentAggregates($query): array
    {
        $baseQuery = clone $query;
        
        return [
            'total_documents' => $baseQuery->count(),
            'by_status' => $baseQuery->select('status_id', DB::raw('count(*) as count'))
                ->groupBy('status_id')
                ->with('status')
                ->get()
                ->mapWithKeys(fn($item) => [$item->status->name ?? 'Unknown' => $item->count]),
            'by_department' => $baseQuery->select('department_id', DB::raw('count(*) as count'))
                ->groupBy('department_id')
                ->with('department')
                ->get()
                ->mapWithKeys(fn($item) => [$item->department->name ?? 'Unknown' => $item->count]),
            'avg_processing_time' => $baseQuery->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
                ->value('avg_hours') ?? 0
        ];
    }

    /**
     * Get user aggregates
     */
    protected function getUserAggregates($query): array
    {
        return [
            'total_users' => $query->count(),
            'active_users' => $query->whereHas('documents', function($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })->count(),
            'by_department' => $query->select('department_id', DB::raw('count(*) as count'))
                ->groupBy('department_id')
                ->with('department')
                ->get()
                ->mapWithKeys(fn($item) => [$item->department->name ?? 'Unknown' => $item->count])
        ];
    }

    /**
     * Get department aggregates
     */
    protected function getDepartmentAggregates($query): array
    {
        return [
            'total_departments' => $query->count(),
            'active_departments' => $query->whereHas('documents', function($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })->count(),
            'documents_per_department' => $query->withCount('documents')
                ->get()
                ->mapWithKeys(fn($item) => [$item->name => $item->documents_count])
        ];
    }

    /**
     * Format data for line chart
     */
    protected function formatLineChartData(Collection $data): array
    {
        // Implementation for line chart formatting
        return [
            'labels' => $data->pluck('created_at')->map(fn($date) => $date->format('Y-m-d'))->unique()->values(),
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => $data->groupBy(fn($item) => $item->created_at->format('Y-m-d'))
                        ->map(fn($group) => $group->count())
                        ->values()
                ]
            ]
        ];
    }

    /**
     * Format data for bar chart
     */
    protected function formatBarChartData(Collection $data): array
    {
        return $this->formatLineChartData($data);
    }

    /**
     * Format data for pie chart
     */
    protected function formatPieChartData(Collection $data): array
    {
        $grouped = $data->groupBy('status.name')->map(fn($group) => $group->count());
        
        return [
            'labels' => $grouped->keys()->toArray(),
            'data' => $grouped->values()->toArray()
        ];
    }

    /**
     * Format data for doughnut chart
     */
    protected function formatDoughnutChartData(Collection $data): array
    {
        return $this->formatPieChartData($data);
    }

    /**
     * Export to PDF
     */
    protected function exportToPdf(Collection $data, array $aggregates): string
    {
        // Use existing ReportService for PDF generation
        $reportService = app(ReportService::class);
        return $reportService->generateCustomReport($data, $aggregates, 'pdf');
    }

    /**
     * Export to Excel
     */
    protected function exportToExcel(Collection $data, array $aggregates): string
    {
        // Use existing ReportService for Excel generation
        $reportService = app(ReportService::class);
        return $reportService->generateCustomReport($data, $aggregates, 'excel');
    }

    /**
     * Export to CSV
     */
    protected function exportToCsv(Collection $data): string
    {
        $filename = 'custom_report_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $path = storage_path('app/reports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $file = fopen($path, 'w');
        
        // Write headers
        if ($data->isNotEmpty()) {
            fputcsv($file, array_keys($data->first()->toArray()));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row->toArray());
            }
        }
        
        fclose($file);
        
        return $path;
    }
}