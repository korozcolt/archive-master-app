<?php

namespace App\Helpers;

use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentHelper
{
    /**
     * Obtener estadísticas de documentos para una empresa
     */
    public static function getDocumentStats(int $companyId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Document::where('company_id', $companyId);
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        $total = $query->count();
        $pending = $query->clone()->whereHas('status', function ($q) {
            $q->whereIn('name', ['pending', 'in_progress', 'review']);
        })->count();
        
        $completed = $query->clone()->whereHas('status', function ($q) {
            $q->whereIn('name', ['completed', 'approved']);
        })->count();
        
        $overdue = $query->clone()->where('due_at', '<', now())
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['completed', 'approved', 'rejected', 'cancelled']);
            })->count();
        
        return [
            'total' => $total,
            'pending' => $pending,
            'completed' => $completed,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }
    
    /**
     * Obtener documentos por estado para gráficos
     */
    public static function getDocumentsByStatus(int $companyId, ?string $period = null): Collection
    {
        $query = Document::select('status_id', DB::raw('count(*) as count'))
            ->where('company_id', $companyId)
            ->with('status:id,name,color')
            ->groupBy('status_id');
        
        if ($period) {
            $startDate = self::getStartDateForPeriod($period);
            $query->where('created_at', '>=', $startDate);
        }
        
        return $query->get()->map(function ($item) {
            $statusName = $item->status instanceof \App\Models\Status ? $item->status->name : 'Sin estado';
            $statusColor = $item->status instanceof \App\Models\Status ? ($item->status->color ?? '#6B7280') : '#6B7280';
            return [
                'status' => $statusName,
                'count' => $item->count,
                'color' => $statusColor,
            ];
        });
    }
    
    /**
     * Obtener documentos vencidos
     */
    public static function getOverdueDocuments(int $companyId, int $limit = 10): Collection
    {
        return Document::where('company_id', $companyId)
            ->where('due_at', '<', now())
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['completed', 'approved', 'rejected', 'cancelled', 'archived']);
            })
            ->with(['status', 'assignee:id,name', 'category:id,name'])
            ->orderBy('due_at', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($document) {
                $daysOverdue = Carbon::parse($document->due_at)->diffInDays(now());
                $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : 'Sin estado';
                return [
                    'id' => $document->id,
                    'document_number' => $document->document_number,
                    'title' => $document->title,
                    'status' => $statusName,
                    'assignee' => $document->assignee?->name ?? 'Sin asignar',
                    'due_date' => $document->due_at,
                    'days_overdue' => $daysOverdue,
                    'priority' => $document->priority,
                ];
            });
    }
    
    /**
     * Obtener actividad reciente de documentos
     */
    public static function getRecentActivity(int $companyId, int $limit = 10): Collection
    {
        return DB::table('activity_log')
            ->join('documents', 'activity_log.subject_id', '=', 'documents.id')
            ->join('users', 'activity_log.causer_id', '=', 'users.id')
            ->where('documents.company_id', $companyId)
            ->where('activity_log.subject_type', Document::class)
            ->select(
                'activity_log.*',
                'users.name as user_name',
                'documents.document_number',
                'documents.title as document_title'
            )
            ->orderBy('activity_log.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => self::formatActivityDescription($activity),
                    'user' => $activity->user_name,
                    'document_number' => $activity->document_number,
                    'document_title' => $activity->document_title,
                    'created_at' => Carbon::parse($activity->created_at),
                    'event' => $activity->event,
                ];
            });
    }
    
    /**
     * Formatear descripción de actividad
     */
    public static function formatActivityDescription($activity): string
    {
        $user = $activity->user_name;
        $document = $activity->document_number;
        
        switch ($activity->event) {
            case 'created':
                return "{$user} creó el documento {$document}";
            case 'updated':
                return "{$user} actualizó el documento {$document}";
            case 'status_changed':
                $properties = json_decode($activity->properties, true);
                $oldStatus = $properties['old_status'] ?? 'desconocido';
                $newStatus = $properties['new_status'] ?? 'desconocido';
                return "{$user} cambió el estado del documento {$document} de {$oldStatus} a {$newStatus}";
            case 'assigned':
                $properties = json_decode($activity->properties, true);
                $newAssignee = $properties['new_assignee'] ?? 'alguien';
                return "{$user} asignó el documento {$document} a {$newAssignee}";
            case 'deleted':
                return "{$user} eliminó el documento {$document}";
            case 'restored':
                return "{$user} restauró el documento {$document}";
            default:
                return "{$user} realizó una acción en el documento {$document}";
        }
    }
    
    /**
     * Obtener métricas de rendimiento de workflow
     */
    public static function getWorkflowMetrics(int $companyId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Document::where('company_id', $companyId);
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        // Tiempo promedio de procesamiento
        $completedDocuments = $query->clone()
            ->whereHas('status', function ($q) {
                $q->whereIn('name', ['completed', 'approved']);
            })
            ->whereNotNull('completed_at')
            ->get();
        
        $averageProcessingTime = 0;
        if ($completedDocuments->count() > 0) {
            $totalTime = $completedDocuments->sum(function ($doc) {
                return Carbon::parse($doc->created_at)->diffInHours(Carbon::parse($doc->completed_at));
            });
            $averageProcessingTime = round($totalTime / $completedDocuments->count(), 2);
        }
        
        // Tasa de cumplimiento de SLA
        $documentsWithSLA = $query->clone()->whereNotNull('due_at')->get();
        $onTimeDocuments = $documentsWithSLA->filter(function ($doc) {
            return $doc->completed_at && Carbon::parse($doc->completed_at)->lte(Carbon::parse($doc->due_at));
        });
        
        $slaComplianceRate = $documentsWithSLA->count() > 0 
            ? round(($onTimeDocuments->count() / $documentsWithSLA->count()) * 100, 2)
            : 0;
        
        return [
            'average_processing_time_hours' => $averageProcessingTime,
            'sla_compliance_rate' => $slaComplianceRate,
            'total_documents' => $query->count(),
            'completed_documents' => $completedDocuments->count(),
            'documents_with_sla' => $documentsWithSLA->count(),
            'on_time_documents' => $onTimeDocuments->count(),
        ];
    }
    
    /**
     * Obtener fecha de inicio para un período
     */
    private static function getStartDateForPeriod(string $period): Carbon
    {
        return match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };
    }
    
    /**
     * Generar datos para gráfico de creación de documentos
     */
    public static function getDocumentCreationChart(int $companyId, int $days = 7): array
    {
        $dates = collect();
        $data = collect();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dates->push($date->format('Y-m-d'));
            
            $count = Document::where('company_id', $companyId)
                ->whereDate('created_at', $date)
                ->count();
            
            $data->push($count);
        }
        
        return [
            'labels' => $dates->map(function ($date) {
                return Carbon::parse($date)->format('d/m');
            })->toArray(),
            'data' => $data->toArray(),
        ];
    }
    
    /**
     * Verificar si un usuario puede acceder a un documento
     */
    public static function canUserAccessDocument(User $user, Document $document): bool
    {
        // Verificar que pertenezcan a la misma empresa
        if ($user->company_id !== $document->company_id) {
            return false;
        }
        
        // Administradores pueden acceder a todo
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Creador o asignado pueden acceder
        if ($user->id === $document->created_by || $user->id === $document->assigned_to) {
            return true;
        }
        
        // Supervisores pueden acceder a documentos de su departamento
        if ($user->hasRole('supervisor') && 
            $document->department_id && 
            $user->department_id === $document->department_id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener próximos vencimientos
     */
    public static function getUpcomingDeadlines(int $companyId, int $days = 7): Collection
    {
        $endDate = now()->addDays($days);
        
        return Document::where('company_id', $companyId)
            ->whereBetween('due_at', [now(), $endDate])
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['completed', 'approved', 'rejected', 'cancelled', 'archived']);
            })
            ->with(['status', 'assignee:id,name', 'category:id,name'])
            ->orderBy('due_at', 'asc')
            ->get()
            ->map(function ($document) {
                $daysUntilDue = now()->diffInDays(Carbon::parse($document->due_at), false);
                $statusName = $document->status instanceof \App\Models\Status ? $document->status->name : 'Sin estado';
                return [
                    'id' => $document->id,
                    'document_number' => $document->document_number,
                    'title' => $document->title,
                    'status' => $statusName,
                    'assignee' => $document->assignee?->name ?? 'Sin asignar',
                    'due_date' => $document->due_at,
                    'days_until_due' => $daysUntilDue,
                    'priority' => $document->priority,
                    'urgency' => $daysUntilDue <= 1 ? 'critical' : ($daysUntilDue <= 3 ? 'high' : 'medium'),
                ];
            });
    }
    
    /**
     * Obtener resumen de carga de trabajo por usuario
     */
    public static function getWorkloadSummary(int $companyId): Collection
    {
        return User::where('company_id', $companyId)
            ->withCount([
                'assignedDocuments as total_assigned',
                'assignedDocuments as pending_documents' => function ($query) {
                    $query->whereHas('status', function ($q) {
                        $q->whereNotIn('name', ['completed', 'approved', 'rejected', 'cancelled', 'archived']);
                    });
                },
                'assignedDocuments as overdue_documents' => function ($query) {
                    $query->where('due_at', '<', now())
                          ->whereHas('status', function ($q) {
                              $q->whereNotIn('name', ['completed', 'approved', 'rejected', 'cancelled', 'archived']);
                          });
                }
            ])
            ->having('total_assigned', '>', 0)
            ->orderBy('pending_documents', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_assigned' => $user->total_assigned,
                    'pending_documents' => $user->pending_documents,
                    'overdue_documents' => $user->overdue_documents,
                    'workload_level' => self::calculateWorkloadLevel($user->pending_documents),
                ];
            });
    }
    
    /**
     * Calcular nivel de carga de trabajo
     */
    private static function calculateWorkloadLevel(int $pendingDocuments): string
    {
        if ($pendingDocuments >= 20) {
            return 'high';
        } elseif ($pendingDocuments >= 10) {
            return 'medium';
        } elseif ($pendingDocuments >= 1) {
            return 'low';
        } else {
            return 'none';
        }
    }
}