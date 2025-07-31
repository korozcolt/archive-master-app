<?php

namespace App\Observers;

use App\Models\Document;
use App\Notifications\DocumentStatusChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;

class DocumentObserver
{
    /**
     * Handle the Document "creating" event.
     */
    public function creating(Document $document): void
    {
        // Asignar valores por defecto si no están establecidos
        if (!$document->document_number) {
            $document->document_number = $this->generateDocumentNumber($document);
        }
        
        if (!$document->created_by && Auth::check()) {
            $document->created_by = Auth::id();
        }
        
        if (!$document->company_id && Auth::check()) {
            $document->company_id = Auth::user()->company_id;
        }
        
        // Establecer prioridad por defecto
        if (!$document->priority) {
            $document->priority = 'medium';
        }
    }

    /**
     * Handle the Document "created" event.
     */
    public function created(Document $document): void
    {
        // Log de actividad
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'document_number' => $document->document_number,
                'title' => $document->title,
                'category_id' => $document->category_id,
                'status_id' => $document->status_id,
            ])
            ->log('created');

        Log::info('Documento creado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'title' => $document->title,
            'created_by' => $document->created_by,
            'company_id' => $document->company_id,
        ]);

        // Notificar al supervisor del departamento si existe
        if ($document->department_id) {
            $this->notifyDepartmentSupervisor($document, 'created');
        }
    }

    /**
     * Handle the Document "updating" event.
     */
    public function updating(Document $document): void
    {
        // Verificar cambios importantes
        $importantChanges = [];
        
        if ($document->isDirty('status_id')) {
            $importantChanges['status'] = [
                'old' => $document->getOriginal('status_id'),
                'new' => $document->status_id
            ];
        }
        
        if ($document->isDirty('assigned_to')) {
            $importantChanges['assignee'] = [
                'old' => $document->getOriginal('assigned_to'),
                'new' => $document->assigned_to
            ];
        }
        
        if ($document->isDirty('priority')) {
            $importantChanges['priority'] = [
                'old' => $document->getOriginal('priority'),
                'new' => $document->priority
            ];
        }
        
        if ($document->isDirty('due_at')) {
            $importantChanges['due_date'] = [
                'old' => $document->getOriginal('due_at'),
                'new' => $document->due_at
            ];
        }
        
        // Almacenar cambios para usar en el evento updated
        $document->setAttribute('_important_changes', $importantChanges);
    }

    /**
     * Handle the Document "updated" event.
     */
    public function updated(Document $document): void
    {
        $importantChanges = $document->getAttribute('_important_changes') ?? [];
        
        // Log de actividad para cambios importantes
        if (!empty($importantChanges)) {
            activity()
                ->performedOn($document)
                ->causedBy(Auth::user())
                ->withProperties([
                    'changes' => $importantChanges,
                    'document_number' => $document->document_number,
                ])
                ->log('updated');
        }

        // Manejar cambio de estado
        if (isset($importantChanges['status'])) {
            $this->handleStatusChange($document, $importantChanges['status']);
        }
        
        // Manejar cambio de asignación
        if (isset($importantChanges['assignee'])) {
            $this->handleAssigneeChange($document, $importantChanges['assignee']);
        }
        
        // Manejar cambio de prioridad
        if (isset($importantChanges['priority'])) {
            $this->handlePriorityChange($document, $importantChanges['priority']);
        }
        
        // Manejar cambio de fecha límite
        if (isset($importantChanges['due_date'])) {
            $this->handleDueDateChange($document, $importantChanges['due_date']);
        }

        Log::info('Documento actualizado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'changes' => $importantChanges,
            'updated_by' => Auth::id(),
        ]);
    }

    /**
     * Handle the Document "deleted" event.
     */
    public function deleted(Document $document): void
    {
        // Log de actividad
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'document_number' => $document->document_number,
                'title' => $document->title,
                'status_id' => $document->status_id,
            ])
            ->log('deleted');

        Log::warning('Documento eliminado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'title' => $document->title,
            'deleted_by' => Auth::id(),
        ]);
    }

    /**
     * Handle the Document "restored" event.
     */
    public function restored(Document $document): void
    {
        // Log de actividad
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'document_number' => $document->document_number,
                'title' => $document->title,
            ])
            ->log('restored');

        Log::info('Documento restaurado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'restored_by' => Auth::id(),
        ]);
    }

    /**
     * Generar número de documento único
     */
    private function generateDocumentNumber(Document $document): string
    {
        $prefix = 'DOC';
        $year = date('Y');
        $month = date('m');
        
        // Obtener el siguiente número secuencial para este mes
        $lastDocument = Document::where('company_id', $document->company_id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = 1;
        if ($lastDocument && $lastDocument->document_number) {
            // Extraer el número secuencial del último documento
            preg_match('/(\d+)$/', $lastDocument->document_number, $matches);
            if (!empty($matches)) {
                $sequence = intval($matches[1]) + 1;
            }
        }
        
        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * Manejar cambio de estado
     */
    private function handleStatusChange(Document $document, array $statusChange): void
    {
        $oldStatus = \App\Models\Status::find($statusChange['old']);
        $newStatus = \App\Models\Status::find($statusChange['new']);
        
        // Verificar que ambos estados existan antes de proceder
        if (!$newStatus) {
            \Illuminate\Support\Facades\Log::warning('Status not found for document status change', [
                'document_id' => $document->id,
                'old_status_id' => $statusChange['old'],
                'new_status_id' => $statusChange['new']
            ]);
            return;
        }
        
        // Enviar notificación de cambio de estado solo si tenemos ambos estados
        if ($document->assigned_to && $oldStatus) {
            $assignee = \App\Models\User::find($document->assigned_to);
            if ($assignee) {
                $assignee->notify(new DocumentStatusChanged(
                    $document,
                    $oldStatus,
                    $newStatus,
                    Auth::user()
                ));
            }
        }
        
        // Log específico para cambio de estado
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'old_status' => $oldStatus?->name,
                'new_status' => $newStatus?->name,
                'document_number' => $document->document_number,
            ])
            ->log('status_changed');
    }

    /**
     * Manejar cambio de asignación
     */
    private function handleAssigneeChange(Document $document, array $assigneeChange): void
    {
        $oldAssignee = $assigneeChange['old'] ? \App\Models\User::find($assigneeChange['old']) : null;
        $newAssignee = $assigneeChange['new'] ? \App\Models\User::find($assigneeChange['new']) : null;
        
        // Notificar al nuevo asignado
        if ($newAssignee) {
            // Aquí se puede crear una notificación específica para asignación
            Log::info('Documento asignado', [
                'document_id' => $document->id,
                'old_assignee' => $oldAssignee?->name,
                'new_assignee' => $newAssignee->name,
            ]);
        }
        
        // Log específico para cambio de asignación
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'old_assignee' => $oldAssignee?->name,
                'new_assignee' => $newAssignee?->name,
                'document_number' => $document->document_number,
            ])
            ->log('assigned');
    }

    /**
     * Manejar cambio de prioridad
     */
    private function handlePriorityChange(Document $document, array $priorityChange): void
    {
        // Si la prioridad cambió a alta, notificar
        if ($priorityChange['new'] === 'high' && $priorityChange['old'] !== 'high') {
            if ($document->assigned_to) {
                $assignee = \App\Models\User::find($document->assigned_to);
                if ($assignee) {
                    Log::info('Prioridad de documento cambiada a alta', [
                        'document_id' => $document->id,
                        'assignee' => $assignee->name,
                    ]);
                }
            }
        }
    }

    /**
     * Manejar cambio de fecha límite
     */
    private function handleDueDateChange(Document $document, array $dueDateChange): void
    {
        // Si se estableció o cambió la fecha límite, verificar si es urgente
        if ($dueDateChange['new']) {
            $dueDate = \Carbon\Carbon::parse($dueDateChange['new']);
            $now = \Carbon\Carbon::now();
            
            // Si la fecha límite es en menos de 24 horas, notificar
            if ($dueDate->diffInHours($now) < 24 && $dueDate->isFuture()) {
                if ($document->assigned_to) {
                    $assignee = \App\Models\User::find($document->assigned_to);
                    if ($assignee) {
                        Log::warning('Documento con fecha límite urgente', [
                            'document_id' => $document->id,
                            'due_date' => $dueDate->toDateTimeString(),
                            'assignee' => $assignee->name,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Notificar al supervisor del departamento
     */
    private function notifyDepartmentSupervisor(Document $document, string $event): void
    {
        // Aquí se puede implementar la lógica para notificar al supervisor
        // Por ahora, solo registramos en el log
        Log::info('Notificación a supervisor de departamento', [
            'document_id' => $document->id,
            'department_id' => $document->department_id,
            'event' => $event,
        ]);
    }
}