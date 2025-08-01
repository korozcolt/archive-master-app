<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOverdueNotifications;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyOverdueDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:notify-overdue {--dry-run : Show what would be done without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for overdue documents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN: Simulating overdue notifications dispatch...');
            $this->simulateDryRun();
            return 0;
        }
        
        $this->info('Dispatching overdue notification jobs...');
        
        // Obtener todas las empresas activas
        $companies = Company::where('is_active', true)->get();
        
        if ($companies->isEmpty()) {
            $this->warn('No active companies found.');
            return 0;
        }
        
        $jobsDispatched = 0;
        
        foreach ($companies as $company) {
            try {
                // Despachar job para cada empresa
                ProcessOverdueNotifications::dispatch($company->id);
                $this->line("Dispatched overdue notifications job for company: {$company->name}");
                $jobsDispatched++;
            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for company {$company->name}: {$e->getMessage()}");
                Log::error('Failed to dispatch overdue notifications job', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // También despachar un job general para documentos sin empresa específica
        try {
            ProcessOverdueNotifications::dispatch();
            $this->line('Dispatched general overdue notifications job');
            $jobsDispatched++;
        } catch (\Exception $e) {
            $this->error("Failed to dispatch general job: {$e->getMessage()}");
        }
        
        $this->info("Successfully dispatched {$jobsDispatched} notification jobs.");
        $this->info('Jobs will be processed asynchronously by the queue workers.');
        
        Log::info('Overdue notifications command completed', [
            'jobs_dispatched' => $jobsDispatched,
            'companies_processed' => $companies->count()
        ]);
        
        return 0;
    }
    
    /**
     * Simulate what would happen in a dry run
     */
    private function simulateDryRun(): void
    {
        $companies = Company::where('is_active', true)->get();
        
        $this->info("Would dispatch jobs for {$companies->count()} companies:");
        
        foreach ($companies as $company) {
            $this->line("  - {$company->name} (ID: {$company->id})");
        }
        
        $this->line('  - General job (no specific company)');
        $this->info('Total jobs that would be dispatched: ' . ($companies->count() + 1));
    }
}
