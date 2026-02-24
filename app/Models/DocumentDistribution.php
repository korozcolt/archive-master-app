<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'company_id',
        'created_by',
        'status',
        'notes',
        'sent_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DocumentDistributionTarget::class)->orderBy('id');
    }
}
