<?php

namespace App\Models;

use App\Enums\ArchivePhase;
use App\Enums\FinalDisposition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'documentary_subseries_id',
        'documentary_type_id',
        'archive_phase',
        'management_years',
        'central_years',
        'historical_action',
        'final_disposition',
        'legal_basis',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'archive_phase' => ArchivePhase::class,
            'management_years' => 'integer',
            'central_years' => 'integer',
            'final_disposition' => FinalDisposition::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documentarySubseries(): BelongsTo
    {
        return $this->belongsTo(DocumentarySubseries::class);
    }

    public function documentaryType(): BelongsTo
    {
        return $this->belongsTo(DocumentaryType::class);
    }
}
