<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Status extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, HasTranslations;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'order',
        'is_initial',
        'is_final',
        'active',
        'settings',
    ];

    public $translatable = [
        'name',
        'description',
    ];
    
    protected $casts = [
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'active' => 'boolean',
        'settings' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($status) {
            if (empty($status->slug)) {
                $name = is_array($status->name) ? reset($status->name) : $status->name;
                $status->slug = Str::slug($name);
            }
        });

        static::updating(function ($status) {
            if ($status->isDirty('name') && empty($status->slug)) {
                $name = is_array($status->name) ? reset($status->name) : $status->name;
                $status->slug = Str::slug($name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_initial', 'is_final', 'active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function fromWorkflows(): HasMany
    {
        return $this->hasMany(WorkflowDefinition::class, 'from_status_id');
    }

    public function toWorkflows(): HasMany
    {
        return $this->hasMany(WorkflowDefinition::class, 'to_status_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInitial($query)
    {
        return $query->where('is_initial', true);
    }

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function getColorHtmlAttribute(): string
    {
        return $this->color ? "bg-{$this->color}-100" : 'bg-gray-100';
    }

    public function getLabelHtmlAttribute(): string
    {
        return '<span class="py-1 px-3 rounded ' . $this->color_html . '">' . $this->name . '</span>';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Status>
     */
    public function getNextStatuses()
    {
        return Status::whereIn('id', $this->fromWorkflows()->active()->pluck('to_status_id'))->get();
    }

    public function canTransitionTo(Status $nextStatus): bool
    {
        return $this->fromWorkflows()
            ->where('to_status_id', $nextStatus->id)
            ->active()
            ->exists();
    }

    public static function fromEnum(DocumentStatus $status, int $companyId): self
    {
        return self::firstOrCreate(
            [
                'company_id' => $companyId,
                'slug' => $status->value
            ],
            [
                'name' => $status->getLabel(),
                'color' => $status->getColor(),
                'icon' => $status->getIcon(),
                'is_initial' => $status === DocumentStatus::Received || $status === DocumentStatus::Draft,
                'is_final' => $status === DocumentStatus::Archived || $status === DocumentStatus::Rejected,
                'active' => true,
            ]
        );
    }
}
