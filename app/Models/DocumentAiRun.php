<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentAiRun extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentAiRunFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'document_id',
        'document_version_id',
        'triggered_by',
        'provider',
        'model',
        'status',
        'task',
        'input_hash',
        'prompt_version',
        'tokens_in',
        'tokens_out',
        'cost_cents',
        'error_message',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_id',
                'document_id',
                'document_version_id',
                'triggered_by',
                'provider',
                'model',
                'status',
                'task',
                'tokens_in',
                'tokens_out',
                'cost_cents',
                'error_message',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function output(): HasOne
    {
        return $this->hasOne(DocumentAiOutput::class);
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentAiRun $run) {
            if (! $run->triggered_by && Auth::check()) {
                $run->triggered_by = Auth::id();
            }
        });
    }
}
