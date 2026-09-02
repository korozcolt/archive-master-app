<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'company_id',
        'requested_by',
        'status',
        'reason',
        'resolved_by',
        'resolution_note',
        'requested_at',
        'responded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForRequester(Builder $query, int $userId): Builder
    {
        return $query->where('requested_by', $userId);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'approved' && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function approve(User $resolver, ?string $note, int $hours): bool
    {
        $this->status = 'approved';
        $this->resolved_by = $resolver->id;
        $this->resolution_note = $note;
        $this->responded_at = now();
        $this->expires_at = now()->addHours($hours);

        return $this->save();
    }

    public function reject(User $resolver, string $note): bool
    {
        $this->status = 'rejected';
        $this->resolved_by = $resolver->id;
        $this->resolution_note = $note;
        $this->responded_at = now();

        return $this->save();
    }
}
