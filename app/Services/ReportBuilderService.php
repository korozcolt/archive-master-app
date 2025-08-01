<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\Department;
use App\Models\Status;
use App\Models\ScheduledReport;
use App\Models\ReportTemplate;
use App\Services\AdvancedFilterService;
use App\Services\PerformanceMetricsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
    protected string $exportFormat = 'pdf';
    protected ?string $chartType = null;
    protected bool $includeAggregates = false;
    protected $reportService;
    protected $advancedFilterService;
    protected $performanceMetricsService;
    protected $template = null;

    public function __construct(
        ReportService $reportService,
        AdvancedFilterService $advancedFilterService,
        PerformanceMetricsService $performanceMetricsService
    ) {
        $this->reportService = $reportService;
        $this->advancedFilterService = $advancedFilterService;
        $this->performanceMetricsService = $performanceMetricsService;
    }

    /**
     * Set the report type
     */
    public function setReportType(string $type): self
    {
        $this->reportType = $type;
        return $this;
    }
    
    public function loadTemplate(ReportTemplate $template)
    {
        $this->template = $template;
        $config = $template->configuration;
        
        if (isset($config['report_type'])) {
            $this->setReportType($config['report_type']);
        }
        
        if (isset($config['filters'])) {
            $this->filters = $config['filters'];
        }
        
        if (isset($config['columns'])) {
            $this->columns = $config['columns'];
        }
        
        if (isset($config['group_by'])) {
            $this->groupBy = $config['group_by'];
        }
        
        if (isset($config['order_by'])) {
            $this->orderBy = $config['order_by'];
        }
        
        if (isset($config['date_range'])) {
            if (isset($config['date_range']['from'])) {
                $this->dateFrom = Carbon::parse($config['date_range']['from']);
            }
            if (isset($config['date_range']['to'])) {
                $this->dateTo = Carbon::parse($config['date_range']['to']);
            }
        }
        
        if (isset($config['export_format'])) {
            $this->exportFormat = $config['export_format'];
        }
        
        if (isset($config['chart_type'])) {
            $this->chartType = $config['chart_type'];
        }
        
        if (isset($config['include_aggregates'])) {
            $this->includeAggregates = $config['include_aggregates'];
        }
        
        // Increment usage count
        $template->incrementUsage();
        
        return $this;
    }
    
    public function saveAsTemplate($name, $description = null, $isPublic = false, $isFavorite = false)
    {
        $config = [
            'report_type' => $this->reportType,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'group_by' => $this->groupBy,
            'order_by' => $this->orderBy,
            'date_range' => [
                'from' => $this->dateFrom?->toISOString(),
                'to' => $this->dateTo?->toISOString()
            ],
            'export_format' => $this->exportFormat,
            'chart_type' => $this->chartType,
            'include_aggregates' => $this->includeAggregates,
        ];
        
        return ReportTemplate::create([
            'name' => $name,
            'description' => $description,
            'report_type' => $this->reportType,
            'configuration' => $config,
            'is_public' => $isPublic,
            'is_favorite' => $isFavorite,
            'user_id' => Auth::id(),
            'company_id' => Auth::user()->company_id ?? null,
        ]);
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
    
    public function addAdvancedFilter($field, $operator, $value = null, $options = [])
    {
        $filter = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'advanced' => true,
            'options' => $options
        ];
        
        // Validate filter using AdvancedFilterService
        if ($this->advancedFilterService->validateFilter($filter, $this->reportType)) {
            $this->filters[] = $filter;
        }
        
        return $this;
    }
    
    public function getFilterSummary()
    {
        return $this->advancedFilterService->buildFilterSummary($this->filters);
    }
    
    public function getAvailableFields()
    {
        return $this->advancedFilterService->getFieldMetadata($this->reportType);
    }
    
    public function getAvailableOperators($fieldType)
    {
        return $this->advancedFilterService->getOperatorsForFieldType($fieldType);
    }
    
    public function getPerformanceMetrics()
    {
        return $this->performanceMetricsService->getOverviewMetrics(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getProductivityMetrics()
    {
        return $this->performanceMetricsService->getProductivityMetrics(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getEfficiencyMetrics()
    {
        return $this->performanceMetricsService->getEfficiencyMetrics(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getQualityMetrics()
    {
        return $this->performanceMetricsService->getQualityMetrics(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getTrendMetrics()
    {
        return $this->performanceMetricsService->getTrendMetrics(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getDepartmentComparison()
    {
        return $this->performanceMetricsService->getDepartmentComparison(
            $this->dateFrom,
            $this->dateTo
        );
    }
    
    public function getUserPerformance($userId = null)
    {
        return $this->performanceMetricsService->getUserPerformanceMetrics(
            $this->dateFrom,
            $this->dateTo,
            $userId
        );
    }
    
    public function getKPIDashboard()
    {
        $filters = [];
        if ($this->dateFrom) {
            $filters['date_from'] = $this->dateFrom->format('Y-m-d');
        }
        if ($this->dateTo) {
            $filters['date_to'] = $this->dateTo->format('Y-m-d');
        }
        
        return $this->performanceMetricsService->getKPIDashboard($filters);
    }

    /**
     * Set columns to include in the report
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    public function setExportFormat(string $format): self
    {
        $this->exportFormat = $format;
        return $this;
    }
    
    public function setChartType(?string $chartType): self
    {
        $this->chartType = $chartType;
        return $this;
    }
    
    public function setIncludeAggregates(bool $include): self
    {
        $this->includeAggregates = $include;
        return $this;
    }
    
    public function getConfiguration(): array
    {
        return [
            'report_type' => $this->reportType,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'group_by' => $this->groupBy,
            'order_by' => $this->orderBy,
            'date_range' => [
                'from' => $this->dateFrom?->toISOString(),
                'to' => $this->dateTo?->toISOString()
            ],
            'export_format' => $this->exportFormat,
            'chart_type' => $this->chartType,
            'include_aggregates' => $this->includeAggregates,
        ];
    }
    
    public function reset(): self
    {
        $this->reportType = 'documents';
        $this->filters = [];
        $this->columns = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->exportFormat = 'pdf';
        $this->chartType = null;
        $this->includeAggregates = false;
        $this->template = null;
        
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
     * Set dynamic date range based on frequency
     */
    public function setDynamicDateRange(string $frequency): self
    {
        $now = now();
        
        switch ($frequency) {
            case 'daily':
                $this->dateFrom = $now->copy()->startOfDay();
                $this->dateTo = $now->copy()->endOfDay();
                break;
            case 'weekly':
                $this->dateFrom = $now->copy()->startOfWeek();
                $this->dateTo = $now->copy()->endOfWeek();
                break;
            case 'monthly':
                $this->dateFrom = $now->copy()->startOfMonth();
                $this->dateTo = $now->copy()->endOfMonth();
                break;
            case 'quarterly':
                $this->dateFrom = $now->copy()->startOfQuarter();
                $this->dateTo = $now->copy()->endOfQuarter();
                break;
            default:
                // Default to last 30 days
                $this->dateFrom = $now->copy()->subDays(30);
                $this->dateTo = $now;
        }
        
        return $this;
    }
    
    /**
     * Configure report from scheduled report configuration
     */
    public function configureFromScheduledReport(array $config): self
    {
        // Set report type
        if (isset($config['report_type'])) {
            $this->setReportType($config['report_type']);
        }
        
        // Set columns
        if (isset($config['columns']) && is_array($config['columns'])) {
            $this->setColumns($config['columns']);
        }
        
        // Apply filters if configured
        if (isset($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (isset($filter['field'], $filter['operator'], $filter['value'])) {
                    $this->addFilter($filter['field'], $filter['operator'], $filter['value']);
                }
            }
        }
        
        // Apply grouping if configured
        if (isset($config['group_by']) && is_array($config['group_by'])) {
            foreach ($config['group_by'] as $group) {
                $this->groupBy($group);
            }
        }
        
        // Apply ordering if configured
        if (isset($config['order_by']) && is_array($config['order_by'])) {
            foreach ($config['order_by'] as $order) {
                if (isset($order['field'])) {
                    $direction = $order['direction'] ?? 'asc';
                    $this->orderBy($order['field'], $direction);
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Get export file path
     */
    public function getExportPath(string $format, string $filename = null): string
    {
        $filename = $filename ?: 'report_' . date('Y-m-d_H-i-s');
        $extension = $this->getFileExtension($format);
        
        return storage_path("app/reports/{$filename}.{$extension}");
    }
    
    /**
     * Get file extension for format
     */
    protected function getFileExtension(string $format): string
    {
        return match($format) {
            'pdf' => 'pdf',
            'excel', 'xlsx' => 'xlsx',
            'csv' => 'csv',
            default => 'pdf'
        };
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
        if (isset($filter['advanced']) && $filter['advanced']) {
            // Use AdvancedFilterService for advanced filters
            $query = $this->advancedFilterService->applyAdvancedFilters($query, [$filter]);
        } else {
            // Legacy filter handling
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
        return $reportService->generateCustomReport($data->toArray(), $aggregates, 'pdf');
    }

    /**
     * Export to Excel
     */
    protected function exportToExcel(Collection $data, array $aggregates): string
    {
        // Use existing ReportService for Excel generation
        $reportService = app(ReportService::class);
        return $reportService->generateCustomReport($data->toArray(), $aggregates, 'excel');
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