<?php

namespace App\Models;

use App\Enums\DocumentAccessLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentaryType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'documentary_subseries_id',
        'code',
        'name',
        'description',
        'access_level_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'access_level_default' => DocumentAccessLevel::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subseries(): BelongsTo
    {
        return $this->belongsTo(DocumentarySubseries::class, 'documentary_subseries_id');
    }

    public function retentionSchedules(): HasMany
    {
        return $this->hasMany(RetentionSchedule::class);
    }
}
