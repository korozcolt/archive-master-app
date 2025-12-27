<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class DocumentLocationHistory extends Model
{
    use HasFactory;

    protected $table = 'document_location_history';

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'physical_location_id',
        'moved_from_location_id',
        'moved_by',
        'movement_type',
        'notes',
        'moved_at',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    // Relationships

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function physicalLocation(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocation::class, 'physical_location_id');
    }

    public function movedFromLocation(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocation::class, 'moved_from_location_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    // Scopes

    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeByMovementType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('moved_at', 'desc')->limit($limit);
    }

    public function scopeForLocation($query, $locationId)
    {
        return $query->where(function ($q) use ($locationId) {
            $q->where('physical_location_id', $locationId)
                ->orWhere('moved_from_location_id', $locationId);
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('moved_by', $userId);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('moved_at', [$startDate, $endDate]);
    }

    // Helper Methods

    /**
     * Verificar si el movimiento es de tipo "stored"
     */
    public function isStored(): bool
    {
        return $this->movement_type === 'stored';
    }

    /**
     * Verificar si el movimiento es de tipo "moved"
     */
    public function isMoved(): bool
    {
        return $this->movement_type === 'moved';
    }

    /**
     * Verificar si el movimiento es de tipo "retrieved"
     */
    public function isRetrieved(): bool
    {
        return $this->movement_type === 'retrieved';
    }

    /**
     * Verificar si el movimiento es de tipo "returned"
     */
    public function isReturned(): bool
    {
        return $this->movement_type === 'returned';
    }

    /**
     * Obtener etiqueta legible del tipo de movimiento
     */
    public function getMovementTypeLabel(): string
    {
        return match ($this->movement_type) {
            'stored' => 'Almacenado',
            'moved' => 'Movido',
            'retrieved' => 'Retirado',
            'returned' => 'Devuelto',
            default => ucfirst($this->movement_type),
        };
    }

    /**
     * Obtener descripción completa del movimiento
     */
    public function getMovementDescription(): string
    {
        $description = $this->getMovementTypeLabel();

        if ($this->isStored()) {
            $locationName = $this->physicalLocation?->full_path ?? 'ubicación desconocida';
            $description .= " en {$locationName}";
        } elseif ($this->isMoved()) {
            $fromLocation = $this->movedFromLocation?->full_path ?? 'ubicación desconocida';
            $toLocation = $this->physicalLocation?->full_path ?? 'ubicación desconocida';
            $description .= " de {$fromLocation} a {$toLocation}";
        } elseif ($this->isRetrieved()) {
            $fromLocation = $this->movedFromLocation?->full_path ?? 'ubicación desconocida';
            $description .= " de {$fromLocation}";
        } elseif ($this->isReturned()) {
            $toLocation = $this->physicalLocation?->full_path ?? 'ubicación desconocida';
            $description .= " a {$toLocation}";
        }

        if ($this->movedBy) {
            $userName = $this->movedBy->name ?? 'Usuario desconocido';
            $description .= " por {$userName}";
        }

        return $description;
    }

    // Hooks

    protected static function booted()
    {
        static::creating(function (DocumentLocationHistory $history) {
            if (empty($history->moved_at)) {
                $history->moved_at = now();
            }

            if (empty($history->moved_by) && Auth::check()) {
                $history->moved_by = Auth::id();
            }
        });
    }
}
