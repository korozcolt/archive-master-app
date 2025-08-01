<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DocumentOverdue;
use App\Services\WorkflowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessOverdueNotifications implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $companyId = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = 'overdue_notifications_processing';
        
        // Evitar procesamiento duplicado
        if (Cache::has($cacheKey)) {
            Log::info('Overdue notifications job already running, skipping.');
            return;
        }
        
        Cache::put($cacheKey, true, 300); // 5 minutos
        
        try {
            $this->processOverdueNotifications();
        } finally {
            Cache::forget($cacheKey);
        }
    }
    
    /**
     * Process overdue document notifications
     */
    private function processOverdueNotifications(): void
    {
        Log::info('Starting overdue notifications processing', [
            'company_id' => $this->companyId
        ]);
        
        $workflowEngine = new WorkflowEngine();
        $overdueDocuments = $workflowEngine->getOverdueDocuments($this->companyId);
        
        if (empty($overdueDocuments)) {
            Log::info('No overdue documents found');
            return;
        }
        
        Log::info('Found overdue documents', [
            'count' => count($overdueDocuments),
            'company_id' => $this->companyId
        ]);
        
        $notificationsSent = 0;
        $errors = 0;
        
        foreach ($overdueDocuments as $overdueData) {
            try {
                $this->processDocumentNotification($overdueData);
                $notificationsSent++;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to process overdue notification', [
                    'document_id' => $overdueData['document']->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        Log::info('Overdue notifications processing completed', [
            'notifications_sent' => $notificationsSent,
            'errors' => $errors,
            'total_processed' => count($overdueDocuments)
        ]);
    }
    
    /**
     * Process notification for a single overdue document
     */
    private function processDocumentNotification(array $overdueData): void
    {
        $document = $overdueData['document'];
        $hoursOverdue = $overdueData['hours_overdue'];
        
        // Verificar si ya se envió una notificación reciente
        $lastNotificationKey = "last_overdue_notification_{$document->id}";
        $lastNotificationTime = Cache::get($lastNotificationKey);
        
        // No enviar notificaciones más frecuentemente que cada 24 horas
        if ($lastNotificationTime && now()->diffInHours($lastNotificationTime) < 24) {
            return;
        }
        
        // Notificar al asignado del documento
        if ($document->assignee) {
            $this->sendNotificationToUser($document->assignee, $document, $hoursOverdue);
        }
        
        // Notificar a supervisores si el documento está muy vencido (más de 48 horas)
        if ($hoursOverdue > 48) {
            $this->notifySupervisors($document, $hoursOverdue);
        }
        
        // Marcar que se envió la notificación
        Cache::put($lastNotificationKey, now(), 86400); // 24 horas
    }
    
    /**
     * Send notification to a specific user
     */
    private function sendNotificationToUser(User $user, $document, int $hoursOverdue): void
    {
        // Verificar permisos antes de enviar
        if (!$user->can('view', $document)) {
            return;
        }
        
        $user->notify(new DocumentOverdue($document, $hoursOverdue));
        
        Log::info('Overdue notification sent', [
            'user_id' => $user->id,
            'document_id' => $document->id,
            'hours_overdue' => $hoursOverdue
        ]);
    }
    
    /**
     * Notify supervisors about critically overdue documents
     */
    private function notifySupervisors($document, int $hoursOverdue): void
    {
        $supervisors = User::role('supervisor')
            ->where('company_id', $document->company_id)
            ->get();
            
        foreach ($supervisors as $supervisor) {
            $this->sendNotificationToUser($supervisor, $document, $hoursOverdue);
        }
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOverdueNotifications job failed', [
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Limpiar cache en caso de fallo
        Cache::forget('overdue_notifications_processing');
    }
}
