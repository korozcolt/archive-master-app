<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'business_calendar_id',
        'code',
        'name',
        'legal_basis',
        'response_term_days',
        'warning_days',
        'escalation_days',
        'remission_deadline_days',
        'requires_subsanation',
        'allows_extension',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'warning_days' => 'array',
            'response_term_days' => 'integer',
            'escalation_days' => 'integer',
            'remission_deadline_days' => 'integer',
            'requires_subsanation' => 'boolean',
            'allows_extension' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
