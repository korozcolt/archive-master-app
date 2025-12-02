<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkflowHistory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'document_id',
        'performed_by',
        'from_status_id',
        'to_status_id',
        'comments',
        'time_spent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['document_id', 'from_status_id', 'to_status_id', 'comments'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
    
    /**
     * Alias for performer relationship
     */
    public function user(): BelongsTo
    {
        return $this->performer();
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    // Scopes
    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopePerformedBy($query, $userId)
    {
        return $query->where('performed_by', $userId);
    }

    public function scopeToStatus($query, $statusId)
    {
        return $query->where('to_status_id', $statusId);
    }

    public function scopeFromStatus($query, $statusId)
    {
        return $query->where('from_status_id', $statusId);
    }

    public function scopeWithComment($query)
    {
        return $query->whereNotNull('comments');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeWithTimeTracking($query)
    {
        return $query->whereNotNull('time_spent');
    }

    // Accessors
    public function getTransitionNameAttribute(): string
    {
        $from = $this->fromStatus?->name ?? 'Inicio';
        $to = $this->toStatus?->name ?? 'Desconocido';

        return "{$from} → {$to}";
    }

    public function getPerformerNameAttribute(): string
    {
        return $this->performer?->name ?? 'Sistema';
    }

    public function getFormattedTimeSpentAttribute(): ?string
    {
        if (!$this->time_spent) {
            return null;
        }

        // Convertir minutos a formato hh:mm
        $hours = floor($this->time_spent / 60);
        $minutes = $this->time_spent % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getTimestampFormattedAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }

    // Métodos
    public function getWorkflowDefinition(): ?WorkflowDefinition
    {
        if (!$this->from_status_id || !$this->to_status_id) {
            return null;
        }

        return WorkflowDefinition::where('from_status_id', $this->from_status_id)
            ->where('to_status_id', $this->to_status_id)
            ->first();
    }

    public function wasOnTime(): ?bool
    {
        $workflow = $this->getWorkflowDefinition();

        if (!$workflow || !$workflow->sla_hours) {
            return null;
        }

        $expectedDuration = $workflow->sla_hours * 60; // Convertir horas a minutos

        return $this->time_spent <= $expectedDuration;
    }

    // Calcular el tiempo transcurrido entre cambios de estado para un documento
    public static function calculateTimeSpent(Document $document, Status $fromStatus, Status $toStatus): ?int
    {
        $fromChange = self::where('document_id', $document->id)
            ->where('to_status_id', $fromStatus->id)
            ->latest()
            ->first();

        $toChange = self::where('document_id', $document->id)
            ->where('to_status_id', $toStatus->id)
            ->latest()
            ->first();

        if (!$fromChange || !$toChange) {
            return null;
        }

        // Calcular la diferencia en minutos
        return $fromChange->created_at->diffInMinutes($toChange->created_at);
    }

    // Registrar una transición de workflow
    public static function recordTransition(
        Document $document,
        ?Status $fromStatus,
        Status $toStatus,
        ?User $performer = null,
        ?string $comments = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'document_id' => $document->id,
            'performed_by' => $performer ? $performer->id : (Auth::id() ?? null),
            'from_status_id' => $fromStatus?->id,
            'to_status_id' => $toStatus->id,
            'comments' => $comments,
            'time_spent' => $fromStatus ? self::calculateTimeSpent($document, $fromStatus, $toStatus) : null,
            'metadata' => $metadata,
        ]);
    }
}
