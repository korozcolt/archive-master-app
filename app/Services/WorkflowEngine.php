<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowHistory;
use App\Models\WorkflowDefinition;
use App\Notifications\DocumentStatusChanged;
use App\Events\DocumentUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Exception;

class WorkflowEngine
{
    /**
     * Transicionar un documento a un nuevo estado
     */
    public function transitionDocument(
        Document $document, 
        Status $newStatus, 
        ?string $comment = null,
        ?User $user = null,
        array $options = []
    ): bool {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            throw new Exception('Usuario no autenticado para realizar la transición');
        }

        // Validar que la transición es válida
        if (!$this->canTransition($document, $newStatus, $user)) {
            throw new Exception('Transición no válida o usuario sin permisos');
        }

        // Validar comentarios obligatorios
        $this->validateRequiredComment($document, $newStatus, $comment);

        // Ejecutar hooks pre-transición
        $this->executePreTransitionHooks($document, $document->status, $newStatus, $user, $options);

        DB::beginTransaction();
        
        try {
            $oldStatus = $document->status;
            
            // Crear registro en el historial
            $this->createWorkflowHistory($document, $oldStatus, $newStatus, $user, $comment);
            
            // Actualizar el documento
            $document->update([
                'status_id' => $newStatus->id,
                'assigned_to' => $this->getNextAssignee($document, $newStatus),
            ]);
            
            // Ejecutar hooks post-transición
            $this->executePostTransitionHooks($document, $oldStatus, $newStatus, $user, $options);
            
            DB::commit();
            
            // Log detallado de la transición
            $this->logDetailedTransition($document, $oldStatus, $newStatus, $user, $comment, $options);
            
            // Disparar evento de documento actualizado
            Event::dispatch(new DocumentUpdated($document, $user));
            
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en transición de documento', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verificar si una transición es válida
     */
    public function canTransition(Document $document, Status $newStatus, User $user): bool
    {
        // Verificar que el documento tenga un estado válido
        if (!$document->status instanceof Status) {
            return false;
        }
        
        /** @var Status $currentStatus */
        $currentStatus = $document->status;
        
        // Verificar que el estado actual puede transicionar al nuevo estado
        if (!$currentStatus->canTransitionTo($newStatus)) {
            return false;
        }

        // Verificar permisos del usuario
        $workflowDefinition = $currentStatus->fromWorkflows()
            ->where('to_status_id', $newStatus->id)
            ->where('company_id', $document->company_id)
            ->active()
            ->first();

        if (!$workflowDefinition) {
            return false;
        }

        // Verificar roles permitidos
        if ($workflowDefinition->allowed_roles && !empty($workflowDefinition->allowed_roles)) {
            $userRoles = $user->getRoleNames()->toArray();
            $allowedRoles = is_array($workflowDefinition->allowed_roles) 
                ? $workflowDefinition->allowed_roles 
                : json_decode($workflowDefinition->allowed_roles, true);
                
            if (!array_intersect($userRoles, $allowedRoles)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener estados disponibles para transición
     */
    public function getAvailableTransitions(Document $document, User $user): array
    {
        $availableStatuses = [];
        
        // Verificar que el documento tenga un estado válido
        if (!$document->status instanceof Status) {
            return $availableStatuses;
        }
        
        /** @var Status $currentStatus */
        $currentStatus = $document->status;
        $nextStatuses = $currentStatus->getNextStatuses();
        
        /** @var Status $nextStatus */
        foreach ($nextStatuses as $nextStatus) {
            if ($this->canTransition($document, $nextStatus, $user)) {
                $availableStatuses[] = $nextStatus;
            }
        }
        
        return $availableStatuses;
    }

    /**
     * Crear registro en el historial de workflow
     */
    private function createWorkflowHistory(
        Document $document, 
        Status $fromStatus, 
        Status $toStatus, 
        User $user, 
        ?string $comment
    ): WorkflowHistory {
        return WorkflowHistory::recordTransition(
            $document,
            $fromStatus,
            $toStatus,
            $user,
            $comment
        );
    }

    /**
     * Obtener el siguiente asignado basado en reglas de negocio
     */
    private function getNextAssignee(Document $document, Status $newStatus): ?int
    {
        // Lógica para determinar el siguiente asignado
        // Por ahora, mantener el asignado actual o asignar al usuario que hace la transición
        return $document->assigned_to ?? Auth::id();
    }

    /**
     * Ejecutar hooks post-transición
     */
    private function executePostTransitionHooks(
        Document $document, 
        Status $oldStatus, 
        Status $newStatus, 
        User $user,
        array $options = []
    ): void {
        // Enviar notificaciones personalizadas
        $customNotifications = $this->getCustomNotifications($document, $oldStatus, $newStatus);
        $this->sendCustomNotifications($document, $customNotifications, $user);
        
        // Enviar notificaciones estándar
        if ($document->assignee) {
            $document->assignee->notify(new DocumentStatusChanged($document, $oldStatus, $newStatus, $user));
        }

        // Actualizar fechas especiales
        if ($newStatus->is_final) {
            $document->update(['completed_at' => now()]);
        }

        // Verificar SLA
        $this->checkSLACompliance($document, $newStatus);
        
        // Ejecutar reglas de escalamiento automático
        $this->executeAutoEscalation($document);
        
        Log::info('Post-transition hooks executed', [
            'document_id' => $document->id,
            'from_status' => $oldStatus->name,
            'to_status' => $newStatus->name,
            'user_id' => $user->id,
            'custom_notifications_count' => count($customNotifications),
            'options' => $options
        ]);
    }

    /**
     * Verificar cumplimiento de SLA
     */
    private function checkSLACompliance(Document $document, Status $newStatus): void
    {
        // Verificar que el documento tenga un estado válido
        if (!$document->status instanceof Status) {
            return;
        }
        
        /** @var Status $currentStatus */
        $currentStatus = $document->status;
        $workflowDefinition = $currentStatus->fromWorkflows()
            ->where('to_status_id', $newStatus->id)
            ->first();

        if ($workflowDefinition && $workflowDefinition->sla_hours) {
            $slaDeadline = $document->created_at->addHours($workflowDefinition->sla_hours);
            
            if (now()->gt($slaDeadline)) {
                Log::warning('SLA excedido', [
                    'document_id' => $document->id,
                    'sla_hours' => $workflowDefinition->sla_hours,
                    'deadline' => $slaDeadline,
                ]);
                
                // Aquí se pueden agregar más acciones como notificaciones especiales
            }
        }
    }

    /**
     * Obtener documentos con SLA vencido
     */
    public function getOverdueDocuments(): array
    {
        $overdueDocuments = [];
        
        $documents = Document::with(['status', 'company'])
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->get();
            
        foreach ($documents as $document) {
            $workflowDefinition = $document->status->fromWorkflows()
                ->where('company_id', $document->company_id)
                ->first();
                
            if ($workflowDefinition && $workflowDefinition->sla_hours) {
                $slaDeadline = $document->created_at->addHours($workflowDefinition->sla_hours);
                
                if (now()->gt($slaDeadline)) {
                    $overdueDocuments[] = [
                        'document' => $document,
                        'sla_deadline' => $slaDeadline,
                        'hours_overdue' => now()->diffInHours($slaDeadline),
                    ];
                }
            }
        }
        
        return $overdueDocuments;
    }

    /**
     * Obtener métricas de workflow
     */
    public function getWorkflowMetrics(int $companyId, ?int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_transitions' => WorkflowHistory::whereHas('document', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->where('created_at', '>=', $startDate)->count(),
            
            'avg_processing_time' => $this->getAverageProcessingTime($companyId, $days),
            'sla_compliance_rate' => $this->getSLAComplianceRate($companyId, $days),
        ];
    }

    /**
     * Obtener tiempo promedio de procesamiento
     */
    private function getAverageProcessingTime(int $companyId, int $days): float
    {
        $startDate = now()->subDays($days);
        
        $completedDocuments = Document::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startDate)
            ->get();
            
        if ($completedDocuments->isEmpty()) {
            return 0.0;
        }
        
        $totalHours = 0;
        foreach ($completedDocuments as $document) {
            $totalHours += $document->created_at->diffInHours($document->completed_at);
        }
        
        return round($totalHours / $completedDocuments->count(), 2);
    }

    /**
     * Obtener tasa de cumplimiento de SLA
     */
    private function getSLAComplianceRate(int $companyId, int $days): float
    {
        $startDate = now()->subDays($days);
        
        $documentsWithSLA = Document::where('company_id', $companyId)
            ->where('created_at', '>=', $startDate)
            ->whereHas('status.fromWorkflows', function ($query) {
                $query->whereNotNull('sla_hours');
            })
            ->get();
            
        if ($documentsWithSLA->isEmpty()) {
            return 100.0;
        }
        
        $compliantCount = 0;
        foreach ($documentsWithSLA as $document) {
            $workflowDefinition = $document->status->fromWorkflows()
                ->where('company_id', $companyId)
                ->whereNotNull('sla_hours')
                ->first();
                
            if ($workflowDefinition) {
                $slaDeadline = $document->created_at->addHours($workflowDefinition->sla_hours);
                $completionTime = $document->completed_at ?? now();
                
                if ($completionTime->lte($slaDeadline)) {
                    $compliantCount++;
                }
            }
        }
        
        return round(($compliantCount / $documentsWithSLA->count()) * 100, 2);
    }
    
    /**
     * Validar reglas de negocio antes de transición
     */
    public function validateBusinessRules(Document $document, Status $newStatus, User $user): array
    {
        $errors = [];
        
        // Validar que el documento tenga todos los campos requeridos
        if (empty($document->title)) {
            $errors[] = 'El documento debe tener un título';
        }
        
        if (empty($document->description)) {
            $errors[] = 'El documento debe tener una descripción';
        }
        
        // Validar que el documento tenga archivos adjuntos si es requerido
        if ($newStatus->requires_attachments && $document->versions->isEmpty()) {
            $errors[] = 'El documento debe tener al menos un archivo adjunto';
        }
        
        // Validar permisos específicos del usuario
        if ($newStatus->requires_approval && !$user->hasRole('supervisor')) {
            $errors[] = 'Solo los supervisores pueden aprobar documentos';
        }
        
        // Validar que el documento no esté bloqueado
        if ($document->is_locked && !$user->hasRole('admin')) {
            $errors[] = 'El documento está bloqueado y solo los administradores pueden modificarlo';
        }
        
        return $errors;
    }
    
    /**
     * Ejecutar transición con validaciones avanzadas
     */
    public function executeTransitionWithValidation(
        Document $document, 
        Status $newStatus, 
        ?string $comment = null,
        ?User $user = null
    ): array {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return [
                'success' => false,
                'errors' => ['Usuario no autenticado']
            ];
        }
        
        // Validar reglas de negocio
        $businessRuleErrors = $this->validateBusinessRules($document, $newStatus, $user);
        if (!empty($businessRuleErrors)) {
            return [
                'success' => false,
                'errors' => $businessRuleErrors
            ];
        }
        
        try {
            $this->transitionDocument($document, $newStatus, $comment, $user);
            return [
                'success' => true,
                'message' => 'Transición ejecutada exitosamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Validar comentarios obligatorios
     */
    private function validateRequiredComment(Document $document, Status $newStatus, ?string $comment): void
    {
        $workflowDefinition = $this->getWorkflowDefinition($document, $newStatus);
        
        if ($workflowDefinition && $workflowDefinition->requires_comment && empty($comment)) {
            throw new Exception('Esta transición requiere un comentario obligatorio');
        }
    }

    /**
     * Ejecutar hooks pre-transición
     */
    private function executePreTransitionHooks(
        Document $document, 
        Status $fromStatus, 
        Status $toStatus, 
        User $user,
        array $options = []
    ): void {
        $workflowDefinition = $this->getWorkflowDefinition($document, $toStatus);
        
        // Validar aprobaciones automáticas vs manuales
        if ($workflowDefinition && $workflowDefinition->requires_approval && !($options['force_approval'] ?? false)) {
            if (!$this->hasRequiredApprovals($document, $workflowDefinition)) {
                throw new Exception('Esta transición requiere aprobaciones adicionales');
            }
        }
        
        // Validar timeouts configurables
        if ($workflowDefinition && $workflowDefinition->timeout_hours) {
            $this->validateTimeout($document, $workflowDefinition);
        }
        
        // Ejecutar validaciones personalizadas por tipo de documento
        $this->executeCustomValidations($document, $toStatus, $user);
        
        Log::info('Pre-transition hooks executed', [
            'document_id' => $document->id,
            'from_status' => $fromStatus->name,
            'to_status' => $toStatus->name,
            'user_id' => $user->id,
            'options' => $options
        ]);
    }

    /**
     * Log detallado de transición
     */
    private function logDetailedTransition(
        Document $document, 
        Status $fromStatus, 
        Status $newStatus, 
        User $user, 
        ?string $comment,
        array $options = []
    ): void {
        Log::info('Documento transicionado', [
            'document_id' => $document->id,
            'document_title' => $document->title,
            'document_type' => $document->type?->value,
            'company_id' => $document->company_id,
            'from_status' => $fromStatus->name,
            'to_status' => $newStatus->name,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'comment' => $comment,
            'options' => $options,
            'assigned_to' => $document->assigned_to,
            'priority' => $document->priority?->value,
            'due_date' => $document->due_date,
            'transition_timestamp' => now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Obtener definición de workflow
     */
    private function getWorkflowDefinition(Document $document, Status $toStatus): ?WorkflowDefinition
    {
        return $document->status->fromWorkflows()
            ->where('to_status_id', $toStatus->id)
            ->where('company_id', $document->company_id)
            ->active()
            ->first();
    }

    /**
     * Verificar aprobaciones requeridas
     */
    private function hasRequiredApprovals(Document $document, WorkflowDefinition $workflowDefinition): bool
    {
        // Implementar lógica de aprobaciones
        // Por ahora retorna true, pero se puede extender con un sistema de aprobaciones
        return true;
    }

    /**
     * Validar timeout de transición
     */
    private function validateTimeout(Document $document, WorkflowDefinition $workflowDefinition): void
    {
        if ($workflowDefinition->timeout_hours) {
            $timeoutDeadline = $document->updated_at->addHours($workflowDefinition->timeout_hours);
            
            if (now()->gt($timeoutDeadline)) {
                // Ejecutar delegación automática por timeout
                $this->handleTimeoutDelegation($document, $workflowDefinition);
            }
        }
    }

    /**
     * Manejar delegación automática por timeout
     */
    private function handleTimeoutDelegation(Document $document, WorkflowDefinition $workflowDefinition): void
    {
        Log::warning('Timeout de transición detectado', [
            'document_id' => $document->id,
            'timeout_hours' => $workflowDefinition->timeout_hours,
            'current_assignee' => $document->assigned_to
        ]);
        
        // Implementar lógica de delegación automática
        // Por ejemplo, asignar al supervisor o al siguiente en la jerarquía
    }

    /**
     * Ejecutar validaciones personalizadas por tipo de documento
     */
    private function executeCustomValidations(Document $document, Status $toStatus, User $user): void
    {
        // Validaciones específicas por tipo de documento
        switch ($document->type) {
            case 'contract':
                $this->validateContractTransition($document, $toStatus, $user);
                break;
            case 'invoice':
                $this->validateInvoiceTransition($document, $toStatus, $user);
                break;
            case 'report':
                $this->validateReportTransition($document, $toStatus, $user);
                break;
        }
    }

    /**
     * Validaciones específicas para contratos
     */
    private function validateContractTransition(Document $document, Status $toStatus, User $user): void
    {
        // Implementar validaciones específicas para contratos
        if ($toStatus->name === 'approved' && !$document->hasRequiredSignatures()) {
            throw new Exception('El contrato requiere todas las firmas antes de ser aprobado');
        }
    }

    /**
     * Validaciones específicas para facturas
     */
    private function validateInvoiceTransition(Document $document, Status $toStatus, User $user): void
    {
        // Implementar validaciones específicas para facturas
        if ($toStatus->name === 'paid' && !$document->hasPaymentProof()) {
            throw new Exception('La factura requiere comprobante de pago antes de marcarla como pagada');
        }
    }

    /**
     * Validaciones específicas para reportes
     */
    private function validateReportTransition(Document $document, Status $toStatus, User $user): void
    {
        // Implementar validaciones específicas para reportes
        if ($toStatus->name === 'published' && !$document->hasRequiredApprovals()) {
            throw new Exception('El reporte requiere aprobación antes de ser publicado');
        }
    }

    /**
     * Obtener notificaciones personalizadas por transición
     */
    public function getCustomNotifications(Document $document, Status $fromStatus, Status $toStatus): array
    {
        $notifications = [];
        $workflowDefinition = $this->getWorkflowDefinition($document, $toStatus);
        
        if ($workflowDefinition && $workflowDefinition->custom_notifications) {
            $customNotifications = is_array($workflowDefinition->custom_notifications) 
                ? $workflowDefinition->custom_notifications 
                : json_decode($workflowDefinition->custom_notifications, true);
                
            foreach ($customNotifications as $notification) {
                $notifications[] = [
                    'type' => $notification['type'] ?? 'email',
                    'recipients' => $notification['recipients'] ?? [],
                    'template' => $notification['template'] ?? 'default',
                    'subject' => $notification['subject'] ?? 'Actualización de documento',
                    'delay_minutes' => $notification['delay_minutes'] ?? 0
                ];
            }
        }
        
        return $notifications;
    }

    /**
     * Ejecutar reglas de escalamiento automático
     */
    public function executeAutoEscalation(Document $document): void
    {
        $workflowDefinition = $this->getWorkflowDefinition($document, $document->status);
        
        if ($workflowDefinition && $workflowDefinition->auto_escalation_hours) {
            $escalationDeadline = $document->updated_at->addHours($workflowDefinition->auto_escalation_hours);
            
            if (now()->gt($escalationDeadline)) {
                $this->escalateDocument($document, $workflowDefinition);
            }
        }
    }

    /**
     * Escalar documento automáticamente
     */
    private function escalateDocument(Document $document, WorkflowDefinition $workflowDefinition): void
    {
        Log::info('Escalando documento automáticamente', [
            'document_id' => $document->id,
            'escalation_hours' => $workflowDefinition->auto_escalation_hours
        ]);
        
        // Implementar lógica de escalamiento
        // Por ejemplo, asignar al supervisor o cambiar prioridad
        if ($document->assignee && $document->assignee->supervisor) {
             $document->update(['assigned_to' => $document->assignee->supervisor->id]);
         }
     }

     /**
      * Enviar notificaciones personalizadas
      */
     private function sendCustomNotifications(Document $document, array $notifications, User $user): void
     {
         foreach ($notifications as $notification) {
             // Implementar envío de notificaciones personalizadas
             Log::info('Enviando notificación personalizada', [
                 'document_id' => $document->id,
                 'notification_type' => $notification['type'],
                 'recipients' => $notification['recipients'],
                 'template' => $notification['template'],
                 'delay_minutes' => $notification['delay_minutes']
             ]);
             
             // Aquí se puede implementar la lógica específica para cada tipo de notificación
             // Por ejemplo, envío de emails, SMS, notificaciones push, etc.
         }
     }
 }