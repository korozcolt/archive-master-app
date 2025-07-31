<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Company extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, HasTranslations;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'address',
        'phone',
        'email',
        'website',
        'logo',
        'primary_color',
        'secondary_color',
        'active',
        'settings',
    ];
    
    public $translatable = [
        'name',
        'legal_name',
        'address',
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'legal_name', 'tax_id', 'active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function workflowDefinitions(): HasMany
    {
        return $this->hasMany(WorkflowDefinition::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }

        // Use the SVG default logo
        return asset('storage/images/default-company-logo.svg');
    }

    public function getInitialsAttribute(): string
    {
        // Ensure we're working with a string by getting the current locale's value
        $name = is_array($this->name) ? $this->getTranslation('name', app()->getLocale()) : $this->name;
        $words = explode(' ', $name);
        $initials = '';

        foreach ($words as $word) {
            $initials .= $word[0] ?? '';
        }

        return strtoupper(substr($initials, 0, 3));
    }
}
