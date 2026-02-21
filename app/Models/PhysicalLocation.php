<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PhysicalLocation extends Model
{
    use HasFactory, LogsActivity, Searchable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'template_id',
        'full_path',
        'code',
        'structured_data',
        'qr_code',
        'capacity_total',
        'capacity_used',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'structured_data' => 'array',
        'capacity_total' => 'integer',
        'capacity_used' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_path', 'code', 'structured_data', 'capacity_total', 'capacity_used', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocationTemplate::class, 'template_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'physical_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(DocumentLocationHistory::class, 'physical_location_id');
    }

    // Scopes

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeSearch($query, string $search)
    {
        $driver = DB::getDriverName();

        return $query->where(function ($q) use ($search, $driver) {
            $q->where('full_path', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('qr_code', 'like', "%{$search}%")
                ->when(
                    $driver === 'sqlite',
                    fn ($query) => $query->orWhere('structured_data', 'like', "%{$search}%"),
                    fn ($query) => $query->orWhereRaw('JSON_SEARCH(structured_data, \"one\", ?) IS NOT NULL', ["%{$search}%"])
                );
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->whereRaw('capacity_used < capacity_total')
            ->orWhereNull('capacity_total');
    }

    public function scopeFull($query)
    {
        return $query->whereRaw('capacity_used >= capacity_total')
            ->whereNotNull('capacity_total');
    }

    // Methods

    /**
     * Generar el código único de la ubicación basado en la estructura
     * Ejemplo: "ED-A/P-3/SALA-ARCH/ARM-12/EST-B/CAJA-045"
     */
    public function generateCode(): string
    {
        if (! $this->template || ! $this->structured_data) {
            return 'LOC-'.strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        }

        $levels = $this->template->getOrderedLevels();
        $parts = [];

        foreach ($levels as $level) {
            $levelName = strtolower($level['name']);
            $levelCode = $level['code'] ?? strtoupper(substr($levelName, 0, 3));
            $value = $this->structured_data[$levelName] ?? null;

            if ($value) {
                $parts[] = "{$levelCode}-{$value}";
            }
        }

        return implode('/', $parts);
    }

    /**
     * Generar el path completo legible
     * Ejemplo: "Edificio A / Piso 3 / Archivo / Armario 12 / Estante B / Caja 045"
     */
    public function generateFullPath(): string
    {
        if (! $this->template || ! $this->structured_data) {
            return $this->code ?? 'Ubicación sin definir';
        }

        $levels = $this->template->getOrderedLevels();
        $parts = [];

        foreach ($levels as $level) {
            $levelName = strtolower($level['name']);
            $levelLabel = $level['name'];
            $value = $this->structured_data[$levelName] ?? null;

            if ($value) {
                $parts[] = "{$levelLabel} {$value}";
            }
        }

        return implode(' / ', $parts);
    }

    /**
     * Incrementar la capacidad usada
     */
    public function incrementCapacity(int $amount = 1): bool
    {
        if ($this->capacity_total && $this->capacity_used + $amount > $this->capacity_total) {
            return false;
        }

        $this->capacity_used += $amount;

        return $this->save();
    }

    /**
     * Decrementar la capacidad usada
     */
    public function decrementCapacity(int $amount = 1): bool
    {
        if ($this->capacity_used - $amount < 0) {
            return false;
        }

        $this->capacity_used -= $amount;

        return $this->save();
    }

    /**
     * Verificar si la ubicación está llena
     */
    public function isFull(): bool
    {
        if (! $this->capacity_total) {
            return false;
        }

        return $this->capacity_used >= $this->capacity_total;
    }

    /**
     * Obtener el porcentaje de capacidad usada
     */
    public function getCapacityPercentage(): float
    {
        if (! $this->capacity_total || $this->capacity_total === 0) {
            return 0.0;
        }

        return round(($this->capacity_used / $this->capacity_total) * 100, 2);
    }

    /**
     * Obtener el número de documentos almacenados
     */
    public function getDocumentCount(): int
    {
        return $this->documents()->count();
    }

    /**
     * Generar QR code único para la ubicación
     */
    public function generateQRCode(): string
    {
        return 'LOC-'.strtoupper(substr(md5($this->code.uniqid()), 0, 16));
    }

    // Hooks

    protected static function booted()
    {
        static::creating(function (PhysicalLocation $location) {
            if (empty($location->code)) {
                $location->code = $location->generateCode();
            }

            if (empty($location->full_path)) {
                $location->full_path = $location->generateFullPath();
            }

            if (empty($location->qr_code)) {
                $location->qr_code = $location->generateQRCode();
            }

            if ($location->capacity_used === null) {
                $location->capacity_used = 0;
            }

            if (empty($location->created_by) && Auth::check()) {
                $location->created_by = Auth::id();
            }
        });

        static::updating(function (PhysicalLocation $location) {
            // Regenerar code y full_path si structured_data cambió
            if ($location->isDirty('structured_data')) {
                $location->code = $location->generateCode();
                $location->full_path = $location->generateFullPath();
            }
        });
    }

    // Scout Searchable

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'full_path' => $this->full_path,
            'qr_code' => $this->qr_code,
            'structured_data' => $this->structured_data,
            'notes' => $this->notes,
            'capacity_percentage' => $this->getCapacityPercentage(),
            'is_full' => $this->isFull(),
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function searchableAs(): string
    {
        return 'physical_locations';
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed() && $this->is_active;
    }
}
