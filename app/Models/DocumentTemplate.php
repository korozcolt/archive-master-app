<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentTemplate extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'icon',
        'color',
        'default_category_id',
        'default_status_id',
        'default_workflow_id',
        'default_priority',
        'default_is_confidential',
        'default_tracking_enabled',
        'custom_fields',
        'required_fields',
        'allowed_file_types',
        'max_file_size_mb',
        'default_tags',
        'suggested_tags',
        'default_physical_location_id',
        'document_number_prefix',
        'instructions',
        'help_text',
        'is_active',
        'usage_count',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'required_fields' => 'array',
        'allowed_file_types' => 'array',
        'default_tags' => 'array',
        'suggested_tags' => 'array',
        'default_is_confidential' => 'boolean',
        'default_tracking_enabled' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'max_file_size_mb' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active', 'default_category_id', 'default_status_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'default_status_id');
    }

    public function defaultWorkflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'default_workflow_id');
    }

    public function defaultPhysicalLocation(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocation::class, 'default_physical_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeMostUsed($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function scopeRecentlyUsed($query, $limit = 10)
    {
        return $query->whereNotNull('last_used_at')
            ->orderBy('last_used_at', 'desc')
            ->limit($limit);
    }

    // Methods

    /**
     * Incrementar el contador de uso
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Aplicar la plantilla a un documento
     *
     * @param array $overrides Valores que sobrescriben los defaults
     * @return array
     */
    public function applyToDocument(array $overrides = []): array
    {
        $defaults = [
            'category_id' => $this->default_category_id,
            'status_id' => $this->default_status_id,
            'priority' => $this->default_priority,
            'is_confidential' => $this->default_is_confidential,
            'tracking_enabled' => $this->default_tracking_enabled,
            'physical_location_id' => $this->default_physical_location_id,
        ];

        // Agregar tags por defecto si existen
        if ($this->default_tags) {
            $defaults['tags'] = $this->default_tags;
        }

        // Generar número de documento con prefijo si existe
        if ($this->document_number_prefix) {
            // El prefijo se agregará en el DocumentObserver
            $defaults['_template_prefix'] = $this->document_number_prefix;
        }

        // Merge con overrides
        return array_merge($defaults, $overrides);
    }

    /**
     * Validar datos contra los campos requeridos de la plantilla
     *
     * @param array $data
     * @return array Array de errores (vacío si todo está bien)
     */
    public function validateData(array $data): array
    {
        $errors = [];

        if ($this->required_fields) {
            foreach ($this->required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[$field] = "El campo {$field} es requerido por la plantilla";
                }
            }
        }

        return $errors;
    }

    /**
     * Obtener los campos personalizados con sus valores
     *
     * @param array $values Valores actuales
     * @return array
     */
    public function getCustomFieldsWithValues(array $values = []): array
    {
        if (!$this->custom_fields) {
            return [];
        }

        return collect($this->custom_fields)->map(function ($field) use ($values) {
            $field['value'] = $values[$field['name']] ?? null;
            return $field;
        })->toArray();
    }

    /**
     * Verificar si un archivo es permitido
     *
     * @param string $extension
     * @return bool
     */
    public function isFileTypeAllowed(string $extension): bool
    {
        if (!$this->allowed_file_types) {
            return true; // Si no hay restricciones, todo es permitido
        }

        return in_array(strtolower($extension), array_map('strtolower', $this->allowed_file_types));
    }

    /**
     * Verificar si el tamaño del archivo es permitido
     *
     * @param int $sizeInBytes
     * @return bool
     */
    public function isFileSizeAllowed(int $sizeInBytes): bool
    {
        if (!$this->max_file_size_mb) {
            return true; // Sin límite
        }

        $sizeInMb = $sizeInBytes / 1024 / 1024;
        return $sizeInMb <= $this->max_file_size_mb;
    }

    // Hooks

    protected static function booted()
    {
        static::creating(function (DocumentTemplate $template) {
            if (empty($template->created_by) && Auth::check()) {
                $template->created_by = Auth::id();
            }

            if (empty($template->company_id) && Auth::check()) {
                $template->company_id = Auth::user()->company_id;
            }
        });

        static::updating(function (DocumentTemplate $template) {
            if (Auth::check()) {
                $template->updated_by = Auth::id();
            }
        });
    }
}
