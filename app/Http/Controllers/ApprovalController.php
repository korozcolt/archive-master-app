<?php

namespace App\Http\Controllers;

use App\Models\DocumentApproval;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Mostrar lista de aprobaciones pendientes del usuario
     */
    public function index()
    {
        $user = Auth::user();
        
        $approvals = DocumentApproval::with([
            'document.status',
            'document.creator',
            'workflowDefinition.fromStatus',
            'workflowDefinition.toStatus',
        ])
        ->whereHas('document', function($query) use ($user) {
            $query->where('company_id', $user->company_id);
        })
        ->pending()
        ->forApprover($user->id)
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return view('approvals.index', compact('approvals'));
    }

    /**
     * Mostrar detalle de documento para aprobar
     */
    public function show(Document $document)
    {
        $user = Auth::user();

        // Verificar que el usuario tenga una aprobación pendiente para este documento
        $approval = $document->pendingApprovals()
            ->where('approver_id', $user->id)
            ->with(['workflowDefinition', 'workflowHistory'])
            ->first();

        if (!$approval) {
            abort(403, 'No tienes permisos para aprobar este documento');
        }

        return view('approvals.show', compact('document', 'approval'));
    }

    /**
     * Aprobar documento
     */
    public function approve(Request $request, DocumentApproval $approval)
    {
        // Verificar que la aprobación pertenece al usuario actual
        if ($approval->approver_id !== Auth::id()) {
            abort(403, 'No tienes permisos para aprobar este documento');
        }

        if (!$approval->isPending()) {
            return back()->with('error', 'Esta aprobación ya fue procesada');
        }

        $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Aprobar
            $approval->approve($request->input('comments'));

            // Verificar si todas las aprobaciones del documento están aprobadas
            $pendingApprovals = $approval->document->pendingApprovals()->count();
            
            if ($pendingApprovals === 0) {
                // Todas las aprobaciones completadas, cambiar estado del documento
                $workflowDefinition = $approval->workflowDefinition;
                $document = $approval->document;
                
                $document->update([
                    'status_id' => $workflowDefinition->to_status_id,
                ]);

                // Crear registro en historial de workflow
                $document->workflowHistory()->create([
                    'from_status_id' => $workflowDefinition->from_status_id,
                    'to_status_id' => $workflowDefinition->to_status_id,
                    'performed_by' => Auth::id(),
                    'comments' => 'Documento aprobado',
                ]);
            }

            DB::commit();

            return redirect()
                ->route('approvals.index')
                ->with('success', 'Documento aprobado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al aprobar documento', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Error al aprobar: ' . $e->getMessage());
        }
    }

    /**
     * Rechazar documento
     */
    public function reject(Request $request, DocumentApproval $approval)
    {
        // Verificar que la aprobación pertenece al usuario actual
        if ($approval->approver_id !== Auth::id()) {
            abort(403, 'No tienes permisos para rechazar este documento');
        }

        if (!$approval->isPending()) {
            return back()->with('error', 'Esta aprobación ya fue procesada');
        }

        $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Rechazar
            $approval->reject($request->input('comments'));

            // Al rechazar, el documento puede volver a estado anterior o mantenerse
            // Esto depende de la lógica de negocio
            $document = $approval->document;
            
            // Crear registro en historial
            $document->workflowHistory()->create([
                'from_status_id' => $document->status_id,
                'to_status_id' => $approval->workflowDefinition->from_status_id,
                'performed_by' => Auth::id(),
                'comments' => 'Aprobación rechazada: ' . $request->input('comments'),
            ]);

            // Cancelar todas las aprobaciones pendientes del documento
            $document->pendingApprovals()->update([
                'status' => 'rejected',
                'comments' => 'Cancelado por rechazo de aprobación',
                'responded_at' => now(),
            ]);

            DB::commit();

            return redirect()
                ->route('approvals.index')
                ->with('success', 'Documento rechazado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al rechazar: ' . $e->getMessage());
        }
    }

    /**
     * Ver historial de aprobaciones de un documento
     */
    public function history(Document $document)
    {
        $approvals = $document->approvals()
            ->with(['approver', 'workflowDefinition'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('approvals.history', compact('document', 'approvals'));
    }
}
