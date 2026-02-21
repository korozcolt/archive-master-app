<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\DocumentAiRun;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiObservability extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected static string $view = 'filament.resources.company-resource.pages.ai-observability';

    public array $overview = [];

    public array $providerRows = [];

    public array $dailyRows = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportCsv'),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->loadMetrics();
    }

    protected function loadMetrics(): void
    {
        $companyId = (int) $this->record->id;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $windowStart = now()->subDays(6)->startOfDay();

        $this->overview = [
            'runs_today' => DocumentAiRun::query()
                ->where('company_id', $companyId)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'runs_success_month' => DocumentAiRun::query()
                ->where('company_id', $companyId)
                ->where('status', 'success')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
            'runs_failed_month' => DocumentAiRun::query()
                ->where('company_id', $companyId)
                ->where('status', 'failed')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
            'cost_month_cents' => (int) DocumentAiRun::query()
                ->where('company_id', $companyId)
                ->where('status', 'success')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('cost_cents'),
        ];

        $providerData = DocumentAiRun::query()
            ->selectRaw('provider, COUNT(*) as runs_total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as runs_success")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as runs_failed")
            ->selectRaw('SUM(COALESCE(cost_cents, 0)) as cost_cents_total')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('provider')
            ->get();

        $this->providerRows = $providerData->map(fn ($row): array => [
            'provider' => strtoupper((string) $row->provider),
            'runs_total' => (int) $row->runs_total,
            'runs_success' => (int) $row->runs_success,
            'runs_failed' => (int) $row->runs_failed,
            'cost_human' => '$'.number_format(((int) $row->cost_cents_total) / 100, 2),
        ])->values()->all();

        $dailyAggregate = DocumentAiRun::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as runs_total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as runs_success")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as runs_failed")
            ->selectRaw('SUM(COALESCE(cost_cents, 0)) as cost_cents_total')
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $windowStart)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $rows = [];
        foreach (CarbonPeriod::create($windowStart, now()->endOfDay()) as $date) {
            $dayKey = $date->toDateString();
            $row = $dailyAggregate->get($dayKey);

            $rows[] = [
                'day' => Carbon::parse($dayKey)->format('d/m/Y'),
                'runs_total' => (int) ($row->runs_total ?? 0),
                'runs_success' => (int) ($row->runs_success ?? 0),
                'runs_failed' => (int) ($row->runs_failed ?? 0),
                'cost_human' => '$'.number_format(((int) ($row->cost_cents_total ?? 0)) / 100, 2),
            ];
        }

        $this->dailyRows = $rows;
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'ai-observability-company-'.$this->record->id.'-'.now()->format('Ymd_His').'.csv';
        $overview = $this->overview;
        $providerRows = $this->providerRows;
        $dailyRows = $this->dailyRows;

        return response()->streamDownload(function () use ($overview, $providerRows, $dailyRows): void {
            $handle = fopen('php://output', 'w');

            if (! $handle) {
                return;
            }

            fputcsv($handle, ['Overview']);
            fputcsv($handle, ['runs_today', $overview['runs_today'] ?? 0]);
            fputcsv($handle, ['runs_success_month', $overview['runs_success_month'] ?? 0]);
            fputcsv($handle, ['runs_failed_month', $overview['runs_failed_month'] ?? 0]);
            fputcsv($handle, ['cost_month_cents', $overview['cost_month_cents'] ?? 0]);
            fputcsv($handle, []);

            fputcsv($handle, ['Provider', 'Runs Total', 'Runs Success', 'Runs Failed', 'Cost']);
            foreach ($providerRows as $row) {
                fputcsv($handle, [
                    $row['provider'] ?? '',
                    $row['runs_total'] ?? 0,
                    $row['runs_success'] ?? 0,
                    $row['runs_failed'] ?? 0,
                    $row['cost_human'] ?? '$0.00',
                ]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Day', 'Runs Total', 'Runs Success', 'Runs Failed', 'Cost']);
            foreach ($dailyRows as $row) {
                fputcsv($handle, [
                    $row['day'] ?? '',
                    $row['runs_total'] ?? 0,
                    $row['runs_success'] ?? 0,
                    $row['runs_failed'] ?? 0,
                    $row['cost_human'] ?? '$0.00',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
