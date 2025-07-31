<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProcessDocumentBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutos
    public int $tries = 3;
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $documentIds,
        public string $action,
        public array $parameters = [],
        public ?int $userId = null
    ) {
        $this->onQueue('document-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowEngine $workflowEngine): void
    {
        Log::info('Iniciando procesamiento de lote de documentos', [
            'document_count' => count($this->documentIds),
            'action' => $this->action,
            'user_id' => $this->userId,
        ]);

        $user = $this->userId ? User::find($this->userId) : null;
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($this->documentIds as $documentId) {
                try {
                    $document = Document::find($documentId);
                    
                    if (!$document) {
                        $errors[] = "Documento ID {$documentId} no encontrado";
                        $errorCount++;
                        continue;
                    }

                    $this->processDocument($document, $workflowEngine, $user);
                    $successCount++;

                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "Error procesando documento ID {$documentId}: {$e->getMessage()}";
                    
                    Log::error('Error en procesamiento de documento individual', [
                        'document_id' => $documentId,
                        'action' => $this->action,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Procesamiento de lote completado', [
                'total_documents' => count($this->documentIds),
                'successful' => $successCount,
                'errors' => $errorCount,
                'action' => $this->action,
            ]);

            // Notificar al usuario si está disponible
            if ($user) {
                $this->notifyUser($user, $successCount, $errorCount, $errors);
            }

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error crítico en procesamiento de lote', [
                'action' => $this->action,
                'document_ids' => $this->documentIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Procesar un documento individual
     */
    private function processDocument(Document $document, WorkflowEngine $workflowEngine, ?User $user): void
    {
        switch ($this->action) {
            case 'change_status':
                $this->changeDocumentStatus($document, $workflowEngine, $user);
                break;
                
            case 'assign':
                $this->assignDocument($document, $user);
                break;
                
            case 'update_category':
                $this->updateDocumentCategory($document);
                break;
                
            case 'add_tags':
                $this->addDocumentTags($document);
                break;
                
            case 'update_priority':
                $this->updateDocumentPriority($document);
                break;
                
            case 'set_due_date':
                $this->setDocumentDueDate($document);
                break;
                
            default:
                throw new Exception("Acción no soportada: {$this->action}");
        }
    }

    /**
     * Cambiar estado de documento
     */
    private function changeDocumentStatus(Document $document, WorkflowEngine $workflowEngine, ?User $user): void
    {
        $statusId = $this->parameters['status_id'] ?? null;
        $comment = $this->parameters['comment'] ?? null;
        
        if (!$statusId) {
            throw new Exception('status_id es requerido para cambio de estado');
        }
        
        $newStatus = Status::find($statusId);
        if (!$newStatus) {
            throw new Exception("Estado ID {$statusId} no encontrado");
        }
        
        $workflowEngine->transitionDocument($document, $newStatus, $comment, $user);
    }

    /**
     * Asignar documento
     */
    private function assignDocument(Document $document, ?User $user): void
    {
        $assigneeId = $this->parameters['assignee_id'] ?? null;
        
        if (!$assigneeId) {
            throw new Exception('assignee_id es requerido para asignación');
        }
        
        $assignee = User::find($assigneeId);
        if (!$assignee) {
            throw new Exception("Usuario ID {$assigneeId} no encontrado");
        }
        
        $document->update(['assigned_to' => $assigneeId]);
        
        // Log de la asignación
        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties([
                'old_assignee' => $document->getOriginal('assigned_to'),
                'new_assignee' => $assigneeId,
            ])
            ->log('assigned');
    }

    /**
     * Actualizar categoría de documento
     */
    private function updateDocumentCategory(Document $document): void
    {
        $categoryId = $this->parameters['category_id'] ?? null;
        
        if (!$categoryId) {
            throw new Exception('category_id es requerido para actualización de categoría');
        }
        
        $document->update(['category_id' => $categoryId]);
    }

    /**
     * Agregar etiquetas a documento
     */
    private function addDocumentTags(Document $document): void
    {
        $tagIds = $this->parameters['tag_ids'] ?? [];
        
        if (empty($tagIds)) {
            throw new Exception('tag_ids es requerido para agregar etiquetas');
        }
        
        $document->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * Actualizar prioridad de documento
     */
    private function updateDocumentPriority(Document $document): void
    {
        $priority = $this->parameters['priority'] ?? null;
        
        if (!$priority) {
            throw new Exception('priority es requerido para actualización de prioridad');
        }
        
        $document->update(['priority' => $priority]);
    }

    /**
     * Establecer fecha límite de documento
     */
    private function setDocumentDueDate(Document $document): void
    {
        $dueDate = $this->parameters['due_date'] ?? null;
        
        if (!$dueDate) {
            throw new Exception('due_date es requerido para establecer fecha límite');
        }
        
        $document->update(['due_at' => $dueDate]);
    }

    /**
     * Notificar al usuario sobre el resultado del procesamiento
     */
    private function notifyUser(User $user, int $successCount, int $errorCount, array $errors): void
    {
        // Aquí se puede implementar una notificación personalizada
        // Por ahora, solo registramos en el log
        Log::info('Notificación de procesamiento de lote', [
            'user_id' => $user->id,
            'action' => $this->action,
            'successful' => $successCount,
            'errors' => $errorCount,
            'error_messages' => $errors,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Fallo en procesamiento de lote de documentos', [
            'action' => $this->action,
            'document_ids' => $this->documentIds,
            'parameters' => $this->parameters,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 segundos, 1 minuto, 2 minutos
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'document-batch',
            "action:{$this->action}",
            "user:{$this->userId}",
            "count:" . count($this->documentIds),
        ];
    }
}