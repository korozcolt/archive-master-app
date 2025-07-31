<?php

namespace App\Http\Middleware;

use App\Models\Document;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class CheckDocumentAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $user = $request->user();
        
        // Si no hay usuario autenticado, denegar acceso
        if (!$user) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Obtener el ID del documento de la ruta
        $documentId = $request->route('document') ?? $request->route('id');
        
        if ($documentId) {
            $document = Document::find($documentId);
            
            // Si el documento no existe
            if (!$document) {
                return response()->json([
                    'message' => 'Documento no encontrado'
                ], 404);
            }
            
            // Verificar que el documento pertenece a la empresa del usuario
            if ($document->company_id !== $user->company_id) {
                return response()->json([
                    'message' => 'No tienes permisos para acceder a este documento'
                ], 403);
            }
            
            // Verificar permisos específicos según el método HTTP
            if (!$this->hasPermissionForAction($user, $document, $request->method())) {
                return response()->json([
                    'message' => 'No tienes permisos para realizar esta acción'
                ], 403);
            }
            
            // Agregar el documento al request para uso posterior
            $request->merge(['document_instance' => $document]);
        }

        return $next($request);
    }

    /**
     * Verificar si el usuario tiene permisos para la acción específica
     */
    private function hasPermissionForAction($user, Document $document, string $method): bool
    {
        switch (strtoupper($method)) {
            case 'GET':
                return $this->canView($user, $document);
            case 'POST':
            case 'PUT':
            case 'PATCH':
                return $this->canEdit($user, $document);
            case 'DELETE':
                return $this->canDelete($user, $document);
            default:
                return false;
        }
    }

    /**
     * Verificar si el usuario puede ver el documento
     */
    private function canView($user, Document $document): bool
    {
        // El usuario puede ver si:
        // 1. Es el creador del documento
        // 2. Está asignado al documento
        // 3. Tiene rol de administrador o supervisor
        // 4. Pertenece al mismo departamento (si está configurado)
        
        if ($document->created_by === $user->id) {
            return true;
        }
        
        if ($document->assigned_to === $user->id) {
            return true;
        }
        
        if ($user->hasRole(['admin', 'supervisor'])) {
            return true;
        }
        
        // Verificar si pertenece al mismo departamento
        if ($document->department_id && $user->department_id === $document->department_id) {
            return true;
        }
        
        return false;
    }

    /**
     * Verificar si el usuario puede editar el documento
     */
    private function canEdit($user, Document $document): bool
    {
        // Solo pueden editar:
        // 1. El usuario asignado
        // 2. El creador (si el documento no está en estado final)
        // 3. Administradores
        // 4. Supervisores del departamento
        
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($document->assigned_to === $user->id) {
            return true;
        }
        
        // El creador puede editar solo si no está en estado final
        if ($document->created_by === $user->id && !$this->isInFinalStatus($document)) {
            return true;
        }
        
        // Supervisores del departamento
        if ($user->hasRole('supervisor') && 
            $document->department_id && 
            $user->department_id === $document->department_id) {
            return true;
        }
        
        return false;
    }

    /**
     * Verificar si el usuario puede eliminar el documento
     */
    private function canDelete($user, Document $document): bool
    {
        // Solo pueden eliminar:
        // 1. Administradores
        // 2. El creador (si el documento está en estado inicial)
        
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // El creador puede eliminar solo si está en estado inicial
        if ($document->created_by === $user->id && $this->isInInitialStatus($document)) {
            return true;
        }
        
        return false;
    }

    /**
     * Verificar si el documento está en estado final
     */
    private function isInFinalStatus(Document $document): bool
    {
        $finalStatuses = ['completed', 'approved', 'rejected', 'cancelled', 'archived'];
        $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : '';
        return in_array(strtolower($statusName), $finalStatuses);
    }

    /**
     * Verificar si el documento está en estado inicial
     */
    private function isInInitialStatus(Document $document): bool
    {
        $initialStatuses = ['draft', 'pending', 'created', 'new'];
        $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : '';
        return in_array(strtolower($statusName), $initialStatuses);
    }
}