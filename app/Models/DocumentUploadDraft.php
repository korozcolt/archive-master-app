<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentUploadDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'branch_id',
        'department_id',
        'status',
        'title',
        'description',
        'category_id',
        'status_id',
        'priority',
        'is_confidential',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'current_step',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'current_step' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentUploadDraftItem::class)->orderBy('sort_order');
    }
}
