<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tag extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_tags')
            ->using(DocumentTag::class)
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getColorHtmlAttribute()
    {
        return $this->color ? "bg-{$this->color}-100" : 'bg-gray-100';
    }

    public function getLabelHtmlAttribute()
    {
        return '<span class="py-1 px-3 rounded ' . $this->color_html . '">' . $this->name . '</span>';
    }
}
