<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\GovernanceAlertService;
use Illuminate\Console\Command;

class NotifyOverdueDocuments extends Command
{
    protected $signature = 'documents:notify-overdue
                            {--company= : ID de la empresa específica}
                            {--dry-run : Mostrar lo que se enviaría sin despachar notificaciones}';

    protected $description = 'Procesa alertas de documentos vencidos para la gobernanza documental';

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

        $totalNotifications = 0;

        foreach ($companies as $company) {
            $summary = $this->governanceAlertService->processCompany($company, ['overdue'], $isDryRun);
            $totalNotifications += $summary['overdue'];

            $this->line(sprintf('%s -> vencidos notificados: %d', $company->name, $summary['overdue']));
        }

        $this->info(sprintf('Proceso de alertas por vencimiento completado. Total: %d', $totalNotifications));

        return self::SUCCESS;
    }
}
