<?php

namespace App\Providers;

use App\Models\Document;
use App\Observers\DocumentObserver;
use App\Services\WorkflowEngine;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class DocumentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el WorkflowEngine como singleton
        $this->app->singleton(WorkflowEngine::class, function ($app) {
            return new WorkflowEngine();
        });

        // Registrar otros servicios relacionados con documentos
        $this->registerDocumentServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar el observer para el modelo Document
        Document::observe(DocumentObserver::class);

        // Registrar políticas de autorización
        $this->registerDocumentPolicies();

        // Registrar macros personalizados
        $this->registerMacros();
    }

    /**
     * Registrar servicios relacionados con documentos
     */
    private function registerDocumentServices(): void
    {
        // Aquí se pueden registrar otros servicios como:
        // - Servicio de notificaciones
        // - Servicio de reportes
        // - Servicio de archivos
        // etc.
    }

    /**
     * Registrar políticas de autorización para documentos
     */
    private function registerDocumentPolicies(): void
    {
        // Política para ver documentos
        Gate::define('view-document', function (User $user, Document $document) {
            // El usuario puede ver si:
            // 1. Pertenece a la misma empresa
            // 2. Es el creador, asignado, o tiene rol apropiado
            if ($user->company_id !== $document->company_id) {
                return false;
            }

            return $user->id === $document->created_by ||
                   $user->id === $document->assigned_to ||
                   $user->hasRole(['admin', 'supervisor']) ||
                   ($document->department_id && $user->department_id === $document->department_id);
        });

        // Política para editar documentos
        Gate::define('edit-document', function (User $user, Document $document) {
            if ($user->company_id !== $document->company_id) {
                return false;
            }

            // Administradores pueden editar cualquier documento
            if ($user->hasRole('admin')) {
                return true;
            }

            // El asignado puede editar
            if ($user->id === $document->assigned_to) {
                return true;
            }

            // El creador puede editar si no está en estado final
            if ($user->id === $document->created_by) {
                $finalStatuses = ['completed', 'approved', 'rejected', 'cancelled', 'archived'];
                $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : '';
                return !in_array(strtolower($statusName), $finalStatuses);
            }

            // Supervisores del departamento
            if ($user->hasRole('supervisor') && 
                $document->department_id && 
                $user->department_id === $document->department_id) {
                return true;
            }

            return false;
        });

        // Política para eliminar documentos
        Gate::define('delete-document', function (User $user, Document $document) {
            if ($user->company_id !== $document->company_id) {
                return false;
            }

            // Solo administradores pueden eliminar
            if ($user->hasRole('admin')) {
                return true;
            }

            // El creador puede eliminar solo si está en estado inicial
            if ($user->id === $document->created_by) {
                $initialStatuses = ['draft', 'pending', 'created', 'new'];
                $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : '';
                return in_array(strtolower($statusName), $initialStatuses);
            }

            return false;
        });

        // Política para cambiar estado de documentos
        Gate::define('change-document-status', function (User $user, Document $document) {
            if ($user->company_id !== $document->company_id) {
                return false;
            }

            return $user->hasRole(['admin', 'supervisor']) ||
                   $user->id === $document->assigned_to;
        });

        // Política para asignar documentos
        Gate::define('assign-document', function (User $user, Document $document) {
            if ($user->company_id !== $document->company_id) {
                return false;
            }

            return $user->hasRole(['admin', 'supervisor']) ||
                   $user->id === $document->created_by;
        });

        // Política para ver reportes de documentos
        Gate::define('view-document-reports', function (User $user) {
            return $user->hasRole(['admin', 'supervisor', 'manager']);
        });

        // Política para exportar documentos
        Gate::define('export-documents', function (User $user) {
            return $user->hasRole(['admin', 'supervisor', 'manager']);
        });

        // Política para ver métricas de workflow
        Gate::define('view-workflow-metrics', function (User $user) {
            return $user->hasRole(['admin', 'supervisor']);
        });

        // Política para gestionar definiciones de workflow
        Gate::define('manage-workflow-definitions', function (User $user) {
            return $user->hasRole('admin');
        });
    }

    /**
     * Registrar macros personalizados
     */
    private function registerMacros(): void
    {
        // Macro para filtrar documentos por empresa del usuario autenticado
        \Illuminate\Database\Eloquent\Builder::macro('forCurrentCompany', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            if (\Illuminate\Support\Facades\Auth::check()) {
                return $this->where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id);
            }
            return $this;
        });

        // Macro para filtrar documentos asignados al usuario actual
        \Illuminate\Database\Eloquent\Builder::macro('assignedToCurrentUser', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            if (\Illuminate\Support\Facades\Auth::check()) {
                return $this->where('assigned_to', \Illuminate\Support\Facades\Auth::id());
            }
            return $this;
        });

        // Macro para filtrar documentos creados por el usuario actual
        \Illuminate\Database\Eloquent\Builder::macro('createdByCurrentUser', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            if (\Illuminate\Support\Facades\Auth::check()) {
                return $this->where('created_by', \Illuminate\Support\Facades\Auth::id());
            }
            return $this;
        });

        // Macro para filtrar documentos por estado
        \Illuminate\Database\Eloquent\Builder::macro('withStatus', function ($statusName) {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            return $this->whereHas('status', function ($query) use ($statusName) {
                $query->where('name', $statusName);
            });
        });

        // Macro para filtrar documentos vencidos
        \Illuminate\Database\Eloquent\Builder::macro('overdue', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            return $this->where('due_at', '<', now())
                       ->whereNotIn('status_id', function ($query) {
                           $query->select('id')
                                 ->from('statuses')
                                 ->whereIn('name', ['completed', 'approved', 'rejected', 'cancelled', 'archived']);
                       });
        });

        // Macro para filtrar documentos por prioridad
        \Illuminate\Database\Eloquent\Builder::macro('withPriority', function ($priority) {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            return $this->where('priority', $priority);
        });

        // Macro para incluir relaciones comunes
        \Illuminate\Database\Eloquent\Builder::macro('withCommonRelations', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            return $this->with([
                'status',
                'category',
                'creator:id,name,email',
                'assignee:id,name,email',
                'company:id,name'
            ]);
        });
    }
}