<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowHistory;
use App\Notifications\DocumentStatusChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        ?User $user = null
    ): bool {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            throw new Exception('Usuario no autenticado para realizar la transición');
        }

        // Validar que la transición es válida
        if (!$this->canTransition($document, $newStatus, $user)) {
            throw new Exception('Transición no válida o usuario sin permisos');
        }

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
            $this->executePostTransitionHooks($document, $oldStatus, $newStatus, $user);
            
            DB::commit();
            
            Log::info('Documento transicionado', [
                'document_id' => $document->id,
                'from_status' => $oldStatus->name,
                'to_status' => $newStatus->name,
                'user_id' => $user->id,
            ]);
            
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
        User $user
    ): void {
        // Enviar notificaciones
        if ($document->assignee) {
            $document->assignee->notify(new DocumentStatusChanged($document, $oldStatus, $newStatus, $user));
        }

        // Actualizar fechas especiales
        if ($newStatus->is_final) {
            $document->update(['completed_at' => now()]);
        }

        // Verificar SLA
        $this->checkSLACompliance($document, $newStatus);
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
        // Implementar lógica para obtener documentos con SLA vencido
        return [];
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
        // Implementar cálculo de tiempo promedio
        return 0.0;
    }

    /**
     * Obtener tasa de cumplimiento de SLA
     */
    private function getSLAComplianceRate(int $companyId, int $days): float
    {
        // Implementar cálculo de tasa de cumplimiento
        return 0.0;
    }
}