<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDistributionTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_distribution_id',
        'department_id',
        'assigned_user_id',
        'status',
        'routing_note',
        'follow_up_note',
        'response_note',
        'sent_at',
        'received_at',
        'reviewed_at',
        'responded_at',
        'closed_at',
        'last_activity_at',
        'last_updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(DocumentDistribution::class, 'document_distribution_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
