<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAiSetting extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyAiSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'provider',
        'api_key_encrypted',
        'is_enabled',
        'monthly_budget_cents',
        'daily_doc_limit',
        'max_pages_per_doc',
        'store_outputs',
        'redact_pii',
    ];

    protected $casts = [
        'api_key_encrypted' => 'encrypted',
        'is_enabled' => 'boolean',
        'store_outputs' => 'boolean',
        'redact_pii' => 'boolean',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
