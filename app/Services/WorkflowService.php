<?php

namespace App\Services;

use App\Models\DocumentApproval;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\ApprovalRequested;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WorkflowService - Sistema simplificado de aprobaciones
 *
 * Este servicio se integra con WorkflowDefinition existente
 * para manejar aprobaciones de transiciones de estado
 */
class WorkflowService
{
    /**
     * Crear aprobaciones para una transición de workflow
     *
     * @param Document $document
     * @param WorkflowDefinition $workflowDefinition
     * @param array $approverIds IDs de usuarios que deben aprobar
     * @return array
     */
    public function createApprovals(Document $document, WorkflowDefinition $workflowDefinition, array $approverIds): array
    {
        try {
            DB::beginTransaction();

            if (empty($approverIds)) {
                throw new \Exception('No hay aprobadores especificados');
            }

            // Crear registro en historial de workflow
            $workflowHistory = $document->workflowHistory()->create([
                'from_status_id' => $workflowDefinition->from_status_id,
                'to_status_id' => $workflowDefinition->to_status_id,
                'performed_by' => Auth::user()->id,
                'comments' => 'Aprobación solicitada',
            ]);

            // Crear aprobaciones para cada aprobador
            $approvals = [];
            foreach ($approverIds as $approverId) {
                $approval = DocumentApproval::create([
                    'document_id' => $document->id,
                    'workflow_definition_id' => $workflowDefinition->id,
                    'workflow_history_id' => $workflowHistory->id,
                    'approver_id' => $approverId,
                    'status' => 'pending',
                ]);

                $approvals[] = $approval;

                // Enviar notificación al aprobador
                $approver = User::find($approverId);
                if ($approver) {
                    $approver->notify(new ApprovalRequested($document, $workflowDefinition));
                }
            }

            DB::commit();

            Log::info('Aprobaciones creadas', [
                'document_id' => $document->id,
                'workflow_definition_id' => $workflowDefinition->id,
                'approvals_created' => count($approvals),
            ]);

            return [
                'success' => true,
                'approvals' => $approvals,
                'message' => 'Aprobaciones creadas exitosamente',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear aprobaciones', [
                'document_id' => $document->id ?? null,
                'workflow_definition_id' => $workflowDefinition->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al crear aprobaciones: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener aprobaciones pendientes para un usuario
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingApprovalsForUser(User $user)
    {
        return DocumentApproval::with([
            'document.status',
            'document.creator',
            'workflowDefinition.fromStatus',
            'workflowDefinition.toStatus',
        ])
        ->pending()
        ->forApprover($user->id)
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Verificar si un documento tiene aprobaciones pendientes
     *
     * @param Document $document
     * @return bool
     */
    public function hasPendingApprovals(Document $document): bool
    {
        return $document->pendingApprovals()->exists();
    }

    /**
     * Obtener estadísticas de aprobaciones para un documento
     *
     * @param Document $document
     * @return array
     */
    public function getApprovalStats(Document $document): array
    {
        $approvals = $document->approvals;

        return [
            'total' => $approvals->count(),
            'pending' => $approvals->where('status', 'pending')->count(),
            'approved' => $approvals->where('status', 'approved')->count(),
            'rejected' => $approvals->where('status', 'rejected')->count(),
        ];
    }

    /**
     * Resolver aprobadores basándose en la configuración del workflow
     *
     * @param WorkflowDefinition $workflowDefinition
     * @param Document $document
     * @return array
     */
    public function resolveApprovers(WorkflowDefinition $workflowDefinition, Document $document): array
    {
        $approvalConfig = $workflowDefinition->approval_config;

        if (empty($approvalConfig)) {
            return [];
        }

        $approverIds = [];

        // Si hay aprobadores específicos configurados
        if (isset($approvalConfig['approvers']) && is_array($approvalConfig['approvers'])) {
            $approverIds = array_merge($approverIds, $approvalConfig['approvers']);
        }

        // Si hay roles configurados, obtener usuarios con esos roles
        if (isset($approvalConfig['roles']) && is_array($approvalConfig['roles'])) {
            foreach ($approvalConfig['roles'] as $role) {
                $users = User::role($role)->pluck('id')->toArray();
                $approverIds = array_merge($approverIds, $users);
            }
        }

        // Si está configurado para usar el jefe del departamento
        if (isset($approvalConfig['use_department_head']) && $approvalConfig['use_department_head']) {
            if ($document->department && $document->department->head_user_id) {
                $approverIds[] = $document->department->head_user_id;
            }
        }

        // Si está configurado para usar el responsable de la sucursal
        if (isset($approvalConfig['use_branch_manager']) && $approvalConfig['use_branch_manager']) {
            if ($document->branch && $document->branch->manager_user_id) {
                $approverIds[] = $document->branch->manager_user_id;
            }
        }

        // Eliminar duplicados y el creador del documento
        $approverIds = array_unique($approverIds);
        $approverIds = array_filter($approverIds, function($id) use ($document) {
            return $id != $document->created_by;
        });

        return array_values($approverIds);
    }
}
