<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\WorkflowHistory;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class NotificationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.notifications';
    
    protected static ?string $heading = 'Notificaciones';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    public function getViewData(): array
    {
        $user = Auth::user();
        
        return [
            'notifications' => $this->getRecentNotifications($user),
            'alerts' => $this->getSystemAlerts($user),
            'reminders' => $this->getReminders($user),
        ];
    }
    
    private function getRecentNotifications($user)
    {
        $notifications = collect();
        
        // Documentos asignados recientemente
        $recentAssignments = Document::where('assigned_to', $user->id)
            ->where('updated_at', '>=', Carbon::now()->subHours(24))
            ->with(['status', 'category', 'creator'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($document) {
                return [
                    'id' => 'assignment_' . $document->id,
                    'type' => 'assignment',
                    'icon' => '📋',
                    'title' => 'Documento asignado',
                    'message' => "Se te ha asignado el documento {$document->document_number}: {$document->title}",
                    'time' => $document->updated_at,
                    'time_human' => $document->updated_at->diffForHumans(),
                    'priority' => 'medium',
                    'url' => route('filament.admin.resources.documents.edit', $document),
                    'read' => false,
                ];
            });
        
        // Cambios de estado en documentos del usuario
        $statusChanges = WorkflowHistory::whereHas('document', function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->with(['document', 'fromStatus', 'toStatus', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($history) {
                $isOwner = $history->document->created_by === Auth::id();
                $isAssignee = $history->document->assigned_to === Auth::id();
                
                return [
                    'id' => 'status_' . $history->id,
                    'type' => 'status_change',
                    'icon' => $this->getStatusChangeIcon($history->toStatus),
                    'title' => 'Cambio de estado',
                    'message' => "El documento {$history->document->document_number} cambió de {$history->fromStatus->name} a {$history->toStatus->name}",
                    'time' => $history->created_at,
                    'time_human' => $history->created_at->diffForHumans(),
                    'priority' => $this->getStatusChangePriority($history->toStatus),
                    'url' => route('filament.admin.resources.documents.view', $history->document),
                    'read' => false,
                    'actor' => $history->user->name,
                ];
            });
        
        return $notifications->merge($recentAssignments)
            ->merge($statusChanges)
            ->sortByDesc('time')
            ->take(10)
            ->values();
    }
    
    private function getSystemAlerts($user)
    {
        $alerts = collect();
        
        // Documentos vencidos
        $overdueCount = Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->where('created_at', '<=', Carbon::now()->subDays(7))
            ->count();
        
        if ($overdueCount > 0) {
            $alerts->push([
                'id' => 'overdue_documents',
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'Documentos Vencidos',
                'message' => "Tienes {$overdueCount} documento(s) vencido(s) que requieren atención",
                'priority' => 'high',
                'action_text' => 'Ver documentos',
                'action_url' => route('filament.admin.resources.documents.index', ['tableFilters[assigned_to][value]' => $user->id]),
            ]);
        }
        
        // Documentos próximos a vencer
        $soonDueCount = Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->whereBetween('created_at', [Carbon::now()->subDays(7), Carbon::now()->subDays(5)])
            ->count();
        
        if ($soonDueCount > 0) {
            $alerts->push([
                'id' => 'soon_due_documents',
                'type' => 'info',
                'icon' => '🕐',
                'title' => 'Documentos Próximos a Vencer',
                'message' => "Tienes {$soonDueCount} documento(s) que vencerán pronto",
                'priority' => 'medium',
                'action_text' => 'Revisar',
                'action_url' => route('filament.admin.resources.documents.index', ['tableFilters[assigned_to][value]' => $user->id]),
            ]);
        }
        
        // Documentos sin asignar en la empresa
        if ($user->hasRole(['admin', 'manager'])) {
            $unassignedCount = Document::where('company_id', $user->company_id)
                ->whereNull('assigned_to')
                ->whereHas('status', function ($query) {
                    $query->where('is_final', false);
                })
                ->count();
            
            if ($unassignedCount > 0) {
                $alerts->push([
                    'id' => 'unassigned_documents',
                    'type' => 'warning',
                    'icon' => '👤',
                    'title' => 'Documentos Sin Asignar',
                    'message' => "Hay {$unassignedCount} documento(s) sin asignar en la empresa",
                    'priority' => 'medium',
                    'action_text' => 'Asignar',
                    'action_url' => route('filament.admin.resources.documents.index', ['tableFilters[assigned_to][value]' => null]),
                ]);
            }
        }
        
        return $alerts;
    }
    
    private function getReminders($user)
    {
        $reminders = collect();
        
        // Recordatorio de documentos pendientes de revisión
        $pendingReview = Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('name', 'like', '%revision%')
                    ->orWhere('name', 'like', '%review%');
            })
            ->count();
        
        if ($pendingReview > 0) {
            $reminders->push([
                'id' => 'pending_review',
                'type' => 'reminder',
                'icon' => '👀',
                'title' => 'Documentos Pendientes de Revisión',
                'message' => "Tienes {$pendingReview} documento(s) esperando tu revisión",
                'priority' => 'medium',
                'action_text' => 'Revisar ahora',
                'action_url' => route('filament.admin.resources.documents.index'),
            ]);
        }
        
        // Recordatorio de documentos creados por el usuario
        $myDocumentsPending = Document::where('created_by', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->where('updated_at', '<=', Carbon::now()->subDays(3))
            ->count();
        
        if ($myDocumentsPending > 0) {
            $reminders->push([
                'id' => 'my_documents_pending',
                'type' => 'reminder',
                'icon' => '📝',
                'title' => 'Tus Documentos en Proceso',
                'message' => "Tienes {$myDocumentsPending} documento(s) creado(s) por ti que aún están en proceso",
                'priority' => 'low',
                'action_text' => 'Ver estado',
                'action_url' => route('filament.admin.resources.documents.index', ['tableFilters[created_by][value]' => $user->id]),
            ]);
        }
        
        return $reminders;
    }
    
    private function getStatusChangeIcon($status)
    {
        $statusName = strtolower($status->name);
        
        if (str_contains($statusName, 'aprobado') || str_contains($statusName, 'approved')) {
            return '✅';
        }
        
        if (str_contains($statusName, 'rechazado') || str_contains($statusName, 'rejected')) {
            return '❌';
        }
        
        if (str_contains($statusName, 'proceso') || str_contains($statusName, 'progress')) {
            return '🔄';
        }
        
        if (str_contains($statusName, 'revision') || str_contains($statusName, 'review')) {
            return '👀';
        }
        
        return '📄';
    }
    
    private function getStatusChangePriority($status)
    {
        $statusName = strtolower($status->name);
        
        if (str_contains($statusName, 'rechazado') || str_contains($statusName, 'rejected')) {
            return 'high';
        }
        
        if (str_contains($statusName, 'aprobado') || str_contains($statusName, 'approved')) {
            return 'medium';
        }
        
        return 'low';
    }
}