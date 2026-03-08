<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentarySubseries extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'documentary_series_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentarySeries::class, 'documentary_series_id');
    }

    public function documentaryTypes(): HasMany
    {
        return $this->hasMany(DocumentaryType::class);
    }

    public function retentionSchedules(): HasMany
    {
        return $this->hasMany(RetentionSchedule::class);
    }
}
