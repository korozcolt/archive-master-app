<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApproval extends Model
{
    use HasFactory;
    
    protected $table = 'document_approvals';

    protected $fillable = [
        'document_id',
        'workflow_definition_id',
        'workflow_history_id',
        'approver_id',
        'status',
        'comments',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function workflowHistory(): BelongsTo
    {
        return $this->belongsTo(WorkflowHistory::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForApprover($query, int $userId)
    {
        return $query->where('approver_id', $userId);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(string $comments = null): bool
    {
        $this->status = 'approved';
        $this->comments = $comments;
        $this->responded_at = now();
        return $this->save();
    }

    public function reject(string $comments): bool
    {
        $this->status = 'rejected';
        $this->comments = $comments;
        $this->responded_at = now();
        return $this->save();
    }
}
