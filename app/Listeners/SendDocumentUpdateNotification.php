<?php

namespace App\Listeners;

use App\Events\DocumentUpdated;
use App\Models\User;
use App\Notifications\DocumentUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendDocumentUpdateNotification implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;
    
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'notifications';
    
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DocumentUpdated $event): void
    {
        try {
            $this->sendNotifications($event);
        } catch (\Exception $e) {
            Log::error('Failed to send document update notifications', [
                'document_id' => $event->document->id,
                'updated_by' => $event->updatedBy->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw para que el job se reintente
        }
    }
    
    /**
     * Send notifications to relevant users
     */
    private function sendNotifications(DocumentUpdated $event): void
    {
        $document = $event->document;
        $updatedBy = $event->updatedBy;
        $changes = $event->changes;
        
        // Obtener usuarios que deben ser notificados
        $usersToNotify = $this->getUsersToNotify($document, $updatedBy);
        
        foreach ($usersToNotify as $user) {
            try {
                // Verificar permisos antes de enviar
                if (!$user->can('view', $document)) {
                    continue;
                }
                
                $user->notify(new DocumentUpdate(
                    $document,
                    $updatedBy,
                    $changes,
                    $event->comment
                ));
                
                Log::info('Document update notification sent', [
                    'document_id' => $document->id,
                    'user_id' => $user->id,
                    'updated_by' => $updatedBy->id
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to send notification to user', [
                    'document_id' => $document->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Get users that should be notified about the document update
     */
    private function getUsersToNotify($document, $updatedBy): \Illuminate\Database\Eloquent\Collection
    {
        $userIds = collect();
        
        // Notificar al asignado actual (si no es quien hizo el cambio)
        if ($document->assigned_to && $document->assigned_to !== $updatedBy->id) {
            $userIds->push($document->assigned_to);
        }
        
        // Notificar al creador (si no es quien hizo el cambio)
        if ($document->created_by && $document->created_by !== $updatedBy->id) {
            $userIds->push($document->created_by);
        }
        
        // Notificar a supervisores del departamento
        if ($document->department_id) {
            try {
                $supervisorIds = User::role('supervisor')
                    ->where('company_id', $document->company_id)
                    ->where('department_id', $document->department_id)
                    ->where('id', '!=', $updatedBy->id)
                    ->pluck('id');
                    
                $userIds = $userIds->merge($supervisorIds);
            } catch (\Exception $e) {
                // Rol supervisor no existe, continuar sin error
            }
        }
        
        // Notificar a administradores de la empresa
        try {
            $adminIds = User::role('admin')
                ->where('company_id', $document->company_id)
                ->where('id', '!=', $updatedBy->id)
                ->pluck('id');
                
            $userIds = $userIds->merge($adminIds);
        } catch (\Exception $e) {
            // Rol admin no existe, continuar sin error
        }
        
        // Remover duplicados y obtener usuarios
        return User::whereIn('id', $userIds->unique())
            ->where('is_active', true)
            ->get();
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(DocumentUpdated $event, \Throwable $exception): void
    {
        Log::error('SendDocumentUpdateNotification listener failed', [
            'document_id' => $event->document->id,
            'updated_by' => $event->updatedBy->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
