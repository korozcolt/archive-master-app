<?php

namespace App\Observers;

use App\Events\DocumentUpdated;
use App\Models\Document;
use App\Models\DocumentLocationHistory;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use App\Notifications\DocumentStatusChanged;
use App\Notifications\DocumentAssigned;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        // Establecer tipo de documento digital por defecto
        if (!$document->digital_document_type) {
            $document->digital_document_type = 'copia';
        }

        // Establecer tipo de documento físico por defecto si no está establecido
        // Por defecto es null para que el usuario decida

        // Generar código de tracking público si está habilitado
        if ($document->tracking_enabled && !$document->public_tracking_code) {
            $document->public_tracking_code = $document->generatePublicTrackingCode();
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

        if ($document->isDirty('physical_location_id')) {
            $importantChanges['physical_location'] = [
                'old' => $document->getOriginal('physical_location_id'),
                'new' => $document->physical_location_id
            ];
        }

        // Almacenar cambios para usar en el evento updated
        // Usamos una propiedad estática temporal para evitar persistirlo en DB
        static::$pendingChanges[$document->id ?? 'new'] = $importantChanges;
    }

    /**
     * Temporary storage for important changes
     */
    protected static $pendingChanges = [];

    /**
     * Handle the Document "updated" event.
     */
    public function updated(Document $document): void
    {
        $importantChanges = static::$pendingChanges[$document->id] ?? [];

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

        // Disparar evento DocumentUpdated para notificaciones automáticas
        if (!empty($importantChanges) && Auth::check()) {
            event(new DocumentUpdated(
                $document,
                Auth::user(),
                $importantChanges,
                $document->getAttribute('_update_comment')
            ));
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

        // Manejar cambio de ubicación física
        if (isset($importantChanges['physical_location'])) {
            $this->handlePhysicalLocationChange($document, $importantChanges['physical_location']);
        }

        Log::info('Documento actualizado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'changes' => $importantChanges,
            'updated_by' => Auth::id(),
        ]);

        // Limpiar cambios temporales
        unset(static::$pendingChanges[$document->id]);
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
        $oldStatus = Status::find($statusChange['old']);
        $newStatus = Status::find($statusChange['new']);

        // Verificar que ambos estados existan antes de proceder
        if (!$newStatus) {
            Log::warning('Status not found for document status change', [
                'document_id' => $document->id,
                'old_status_id' => $statusChange['old'],
                'new_status_id' => $statusChange['new']
            ]);
            return;
        }

        // Enviar notificación de cambio de estado solo si tenemos ambos estados
        if ($document->assigned_to && $oldStatus && Auth::check()) {
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
        $oldAssignee = $assigneeChange['old'] ? User::find($assigneeChange['old']) : null;
        $newAssignee = $assigneeChange['new'] ? User::find($assigneeChange['new']) : null;

        // Notificar al nuevo asignado
        if ($newAssignee) {
            $newAssignee->notify(new DocumentAssigned($document, Auth::user()));

            Log::info('Documento asignado - Notificación enviada', [
                'document_id' => $document->id,
                'old_assignee' => $oldAssignee?->name,
                'new_assignee' => $newAssignee->name,
                'assigned_by' => Auth::user()?->name,
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
                $assignee = User::find($document->assigned_to);
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
            $dueDate = Carbon::parse($dueDateChange['new']);
            $now = Carbon::now();

            // Si la fecha límite es en menos de 24 horas, notificar
            if ($dueDate->diffInHours($now) < 24 && $dueDate->isFuture()) {
                if ($document->assigned_to) {
                    $assignee = User::find($document->assigned_to);
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

    /**
     * Manejar cambio de ubicación física
     */
    private function handlePhysicalLocationChange(Document $document, array $locationChange): void
    {
        $oldLocationId = $locationChange['old'];
        $newLocationId = $locationChange['new'];

        // Crear registro en el historial de ubicaciones
        DocumentLocationHistory::create([
            'document_id' => $document->id,
            'physical_location_id' => $newLocationId,
            'moved_from_location_id' => $oldLocationId,
            'moved_by' => Auth::id(),
            'movement_type' => $oldLocationId ? 'moved' : 'stored',
            'notes' => 'Ubicación actualizada automáticamente',
            'moved_at' => now(),
        ]);

        // Actualizar capacidades de las ubicaciones
        if ($oldLocationId) {
            $oldLocation = PhysicalLocation::find($oldLocationId);
            $oldLocation?->decrementCapacity();
        }

        if ($newLocationId) {
            $newLocation = PhysicalLocation::find($newLocationId);
            $newLocation?->incrementCapacity();
        }

        // Log específico para cambio de ubicación
        activity()
            ->performedOn($document)
            ->causedBy(Auth::user())
            ->withProperties([
                'old_location_id' => $oldLocationId,
                'new_location_id' => $newLocationId,
                'old_location_path' => $oldLocationId ? PhysicalLocation::find($oldLocationId)?->full_path : null,
                'new_location_path' => $newLocationId ? PhysicalLocation::find($newLocationId)?->full_path : null,
                'document_number' => $document->document_number,
            ])
            ->log('location_changed');

        Log::info('Ubicación física del documento actualizada', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'old_location_id' => $oldLocationId,
            'new_location_id' => $newLocationId,
            'moved_by' => Auth::id(),
        ]);
    }
}
