<?php

namespace App\Jobs;

use App\Models\ScheduledReport;
use App\Services\ReportBuilderService;
use App\Mail\ScheduledReportMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProcessScheduledReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando procesamiento de reportes programados');
        
        $scheduledReports = ScheduledReport::dueToRun()->get();
        
        foreach ($scheduledReports as $scheduledReport) {
            try {
                $this->processScheduledReport($scheduledReport);
            } catch (\Exception $e) {
                Log::error('Error procesando reporte programado ID: ' . $scheduledReport->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        Log::info('Finalizado procesamiento de reportes programados', [
            'processed_count' => $scheduledReports->count()
        ]);
    }
    
    /**
     * Process a single scheduled report
     */
    private function processScheduledReport(ScheduledReport $scheduledReport): void
    {
        Log::info('Procesando reporte programado: ' . $scheduledReport->name);
        
        $builder = app(ReportBuilderService::class);
        $config = $scheduledReport->report_config;
        
        // Configure the report builder
        $builder->setReportType($config['report_type'] ?? 'documents');
        
        // Set dynamic date range (last period based on frequency)
        $this->setDynamicDateRange($builder, $scheduledReport->schedule_frequency);
        
        // Apply filters
        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                $builder->addFilter($filter['field'], $filter['operator'], $filter['value']);
            }
        }
        
        // Set columns
        if (!empty($config['columns'])) {
            $builder->setColumns($config['columns']);
        }
        
        // Set grouping
        if (!empty($config['group_by'])) {
            $builder->groupBy($config['group_by']);
        }
        
        // Set ordering
        if (!empty($config['order_by'])) {
            foreach ($config['order_by'] as $order) {
                $builder->orderBy($order['field'], $order['direction'] ?? 'asc');
            }
        }
        
        // Generate the report
        $filePath = $builder->export($config['export_format'] ?? 'pdf');
        
        // Send email with the report
        $this->sendReportEmail($scheduledReport, $filePath);
        
        // Mark as run and calculate next run
        $scheduledReport->markAsRun();
        
        // Clean up temporary file
        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
        }
        
        Log::info('Reporte programado procesado exitosamente: ' . $scheduledReport->name);
    }
    
    /**
     * Set dynamic date range based on frequency
     */
    private function setDynamicDateRange(ReportBuilderService $builder, string $frequency): void
    {
        $now = Carbon::now();
        
        switch ($frequency) {
            case 'daily':
                $builder->setDateRange($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay());
                break;
                
            case 'weekly':
                $builder->setDateRange($now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek());
                break;
                
            case 'monthly':
                $builder->setDateRange($now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth());
                break;
                
            case 'quarterly':
                $builder->setDateRange($now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter());
                break;
        }
    }
    
    /**
     * Send report via email
     */
    private function sendReportEmail(ScheduledReport $scheduledReport, string $filePath): void
    {
        $recipients = $scheduledReport->email_recipients;
        
        if (empty($recipients)) {
            Log::warning('No hay destinatarios configurados para el reporte: ' . $scheduledReport->name);
            return;
        }
        
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new ScheduledReportMail($scheduledReport, $filePath));
                Log::info('Reporte enviado por email', [
                    'report' => $scheduledReport->name,
                    'email' => $email
                ]);
            } catch (\Exception $e) {
                Log::error('Error enviando reporte por email', [
                    'report' => $scheduledReport->name,
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
