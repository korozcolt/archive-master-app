<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\GovernanceAlertService;
use Illuminate\Console\Command;

class CheckOverdueDocuments extends Command
{
    protected $signature = 'documents:check-overdue
                            {--company= : ID de la empresa específica}
                            {--notify : Alias compatible; las notificaciones ya se procesan por defecto}
                            {--dry-run : Ejecutar sin enviar notificaciones}';

    protected $description = 'Verifica documentos vencidos y procesa alertas SLA configurables';

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

            $this->line(sprintf('%s -> vencidos detectados/notificados: %d', $company->name, $summary['overdue']));
        }

        $this->info(sprintf('Verificación de documentos vencidos completada. Total: %d', $totalNotifications));

        return self::SUCCESS;
    }
}
