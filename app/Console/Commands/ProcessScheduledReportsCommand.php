<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledReports;
use Illuminate\Console\Command;

class ProcessScheduledReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa y envía los reportes programados que están listos para ejecutar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando procesamiento de reportes programados...');
        
        // Dispatch the job to process scheduled reports
        ProcessScheduledReports::dispatch();
        
        $this->info('Job de procesamiento de reportes programados enviado a la cola.');
        
        return Command::SUCCESS;
    }
}
