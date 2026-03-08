<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'country_code',
        'timezone',
        'weekend_days',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'weekend_days' => 'array',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(BusinessCalendarDay::class);
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
