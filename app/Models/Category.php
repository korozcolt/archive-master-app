<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'order',
        'active',
        'settings',
    ];

    public $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $name = is_array($category->name) ? ($category->name['es'] ?? $category->name['en'] ?? reset($category->name)) : $category->name;
                $category->slug = Str::slug($name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $name = is_array($category->name) ? ($category->name['es'] ?? $category->name['en'] ?? reset($category->name)) : $category->name;
                $category->slug = Str::slug($name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    public function getColorHtmlAttribute(): string
    {
        return $this->color ? "bg-{$this->color}-100" : 'bg-gray-100';
    }

    public function getLabelHtmlAttribute(): string
    {
        return '<span class="py-1 px-3 rounded '.$this->color_html.'">'.$this->name.'</span>';
    }

    public function getAllChildrenIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }

        return $ids;
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
