<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PhysicalLocationTemplate extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'levels',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'levels' => 'array',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'levels', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(PhysicalLocation::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    // Methods

    /**
     * Obtener un nivel específico por su código
     */
    public function getLevelByCode(string $code): ?array
    {
        if (!$this->levels) {
            return null;
        }

        foreach ($this->levels as $level) {
            if (isset($level['code']) && $level['code'] === $code) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Obtener todos los nombres de niveles
     */
    public function getLevelNames(): array
    {
        if (!$this->levels) {
            return [];
        }

        return array_map(function ($level) {
            return $level['name'] ?? '';
        }, $this->levels);
    }

    /**
     * Validar que los datos estructurados cumplan con la plantilla
     */
    public function validateStructuredData(array $data): bool
    {
        if (!$this->levels) {
            return false;
        }

        // Verificar que todos los niveles requeridos estén presentes
        foreach ($this->levels as $level) {
            $levelName = strtolower($level['name']);
            $isRequired = $level['required'] ?? false;

            if ($isRequired && !isset($data[$levelName])) {
                return false;
            }
        }

        // Verificar que no haya niveles extra no definidos en la plantilla
        $validLevelNames = array_map(function ($level) {
            return strtolower($level['name']);
        }, $this->levels);

        foreach (array_keys($data) as $key) {
            if (!in_array(strtolower($key), $validLevelNames)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener los niveles ordenados
     */
    public function getOrderedLevels(): array
    {
        if (!$this->levels) {
            return [];
        }

        $levels = $this->levels;
        usort($levels, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $levels;
    }

    /**
     * Verificar si un nivel es requerido
     */
    public function isLevelRequired(string $levelName): bool
    {
        if (!$this->levels) {
            return false;
        }

        foreach ($this->levels as $level) {
            if (strtolower($level['name']) === strtolower($levelName)) {
                return $level['required'] ?? false;
            }
        }

        return false;
    }
}
