<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\GovernanceAlertService;
use Illuminate\Console\Command;

class CheckDueDocuments extends Command
{
    protected $signature = 'documents:check-due
                            {--company= : ID de la empresa específica}
                            {--dry-run : Ejecutar sin enviar notificaciones}';

    protected $description = 'Procesa alertas por vencer y de archivo para la gobernanza documental';

    public function __construct(protected GovernanceAlertService $governanceAlertService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyId = $this->option('company');
        $isDryRun = (bool) $this->option('dry-run');

        $companies = Company::query()
            ->when($companyId, fn ($query) => $query->whereKey($companyId))
            ->where('active', true)
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No se encontraron empresas activas para procesar.');

            return self::SUCCESS;
        }

        $totals = [
            'due_soon' => 0,
            'ready_for_archive' => 0,
            'archive_incomplete' => 0,
        ];

        foreach ($companies as $company) {
            $summary = $this->governanceAlertService->processCompany($company, ['due', 'archive'], $isDryRun);

            $totals['due_soon'] += $summary['due_soon'];
            $totals['ready_for_archive'] += $summary['ready_for_archive'];
            $totals['archive_incomplete'] += $summary['archive_incomplete'];

            $this->line(sprintf(
                '%s -> por vencer: %d, listos para archivo: %d, archivo incompleto: %d',
                $company->name,
                $summary['due_soon'],
                $summary['ready_for_archive'],
                $summary['archive_incomplete'],
            ));
        }

        $this->info('Proceso de alertas por vencer y archivo completado.');
        $this->table(
            ['Tipo', 'Total'],
            [
                ['Por vencer', $totals['due_soon']],
                ['Listos para archivo', $totals['ready_for_archive']],
                ['Archivo incompleto', $totals['archive_incomplete']],
            ]
        );

        return self::SUCCESS;
    }
}
