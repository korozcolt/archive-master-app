<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'url',
        'events',
        'name',
        'secret',
        'active',
        'retry_attempts',
        'timeout',
        'last_triggered_at',
        'failed_attempts',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'retry_attempts' => 'integer',
        'timeout' => 'integer',
        'failed_attempts' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Relación con Company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con User (creador del webhook)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para webhooks activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para webhooks por empresa
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para webhooks suscritos a un evento específico
     */
    public function scopeSubscribedToEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    /**
     * Marcar webhook como disparado
     */
    public function markAsTriggered(): void
    {
        $this->update([
            'last_triggered_at' => now(),
        ]);
    }

    /**
     * Incrementar contador de fallos
     */
    public function incrementFailures(): void
    {
        $this->increment('failed_attempts');
    }

    /**
     * Resetear contador de fallos
     */
    public function resetFailures(): void
    {
        $this->update(['failed_attempts' => 0]);
    }

    /**
     * Verificar si el webhook tiene el evento suscrito
     */
    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }
}
