<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Tag extends Model
{
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'active',
    ];

    public $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $name = is_array($tag->name) ? reset($tag->name) : $tag->name;
                $tag->slug = Str::slug($name);
            }
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name') && empty($tag->slug)) {
                $name = is_array($tag->name) ? reset($tag->name) : $tag->name;
                $tag->slug = Str::slug($name);
            }
        });
    }

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

    public function getColorHtmlAttribute(): string
    {
        return $this->color ? "bg-{$this->color}-100" : 'bg-gray-100';
    }

    public function getLabelHtmlAttribute(): string
    {
        return '<span class="py-1 px-3 rounded '.$this->color_html.'">'.$this->name.'</span>';
    }

    public function setNameAttribute(array|string|null $value): void
    {
        $this->attributes['name'] = $this->normalizeTranslatableValue($value);
    }

    public function setDescriptionAttribute(array|string|null $value): void
    {
        $this->attributes['description'] = $this->normalizeTranslatableValue($value);
    }

    private function normalizeTranslatableValue(array|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->looksLikeJsonObject($value)) {
            return $value;
        }

        return json_encode(
            [app()->getLocale() => $value],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function looksLikeJsonObject(string $value): bool
    {
        if (! str_starts_with(trim($value), '{')) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
