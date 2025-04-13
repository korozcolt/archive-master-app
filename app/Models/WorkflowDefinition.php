<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

class WorkflowDefinition extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'from_status_id',
        'to_status_id',
        'roles_allowed',
        'requires_approval',
        'requires_comment',
        'sla_hours',
        'active',
        'settings',
    ];

    protected $casts = [
        'roles_allowed' => 'json',
        'requires_approval' => 'boolean',
        'requires_comment' => 'boolean',
        'active' => 'boolean',
        'settings' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'from_status_id', 'to_status_id', 'active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    /**
     * Obtener los estados relacionados con esta definición de flujo
     * Esta es una relación personalizada para el RelationManager de Filament
     */
    public function statuses()
    {
        // Retornamos una consulta que incluye tanto el estado de origen como el de destino
        return Status::where('company_id', $this->company_id)
            ->where(function($query) {
                $query->where('id', $this->from_status_id)
                      ->orWhere('id', $this->to_status_id);
            });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeFromStatus($query, $statusId)
    {
        return $query->where('from_status_id', $statusId);
    }

    public function scopeToStatus($query, $statusId)
    {
        return $query->where('to_status_id', $statusId);
    }

    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    public function scopeRequiresComment($query)
    {
        return $query->where('requires_comment', true);
    }

    public function scopeWithRole($query, $roleId)
    {
        return $query->whereJsonContains('roles_allowed', $roleId);
    }

    // Accessors
    public function getTransitionNameAttribute(): string
    {
        return $this->fromStatus?->name . ' → ' . $this->toStatus?->name;
    }

    public function getRolesAllowedTextAttribute(): string
    {
        if (empty($this->roles_allowed)) {
            return 'Todos los roles';
        }

        return collect($this->roles_allowed)
            ->map(function ($role) {
                // Si usamos el enum Role para representar roles
                try {
                    return \App\Enums\Role::from($role)->getLabel();
                } catch (\ValueError $e) {
                    // Si estamos usando nombres de roles como strings
                    return ucfirst($role);
                }
            })
            ->implode(', ');
    }

    public function getStatusesFullNameAttribute(): string
    {
        return ($this->fromStatus?->name ?? 'Desconocido') . ' → ' . ($this->toStatus?->name ?? 'Desconocido');
    }

    // Métodos
    public function isAllowedForUser(User $user): bool
    {
        // Si no hay roles específicos, cualquiera puede realizar esta transición
        if (empty($this->roles_allowed)) {
            return true;
        }

        // Verificar si el usuario tiene alguno de los roles permitidos
        foreach ($this->roles_allowed as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getSlaInMinutes(): ?int
    {
        return $this->sla_hours ? ($this->sla_hours * 60) : null;
    }

    /**
     * Calcula la fecha de vencimiento basada en las horas de SLA
     */
    public function getDueDate(?Carbon $startDate = null): ?Carbon
    {
        if (!$this->sla_hours) {
            return null;
        }

        $startDate = $startDate ?? Carbon::now();

        // Con Carbon, el método addHours sí existe
        return $startDate->copy()->addHours($this->sla_hours);
    }

    /**
     * Crear un workflow predeterminado para un tipo de documento
     */
    public static function createDefaultWorkflow(
        int $companyId,
        Status $fromStatus,
        Status $toStatus,
        ?string $name = null,
        array $rolesAllowed = [],
        bool $requiresApproval = false,
        bool $requiresComment = false,
        ?int $slaHours = null
    ): self {
        return self::create([
            'company_id' => $companyId,
            'name' => $name ?? "{$fromStatus->name} a {$toStatus->name}",
            'description' => "Transición de {$fromStatus->name} a {$toStatus->name}",
            'from_status_id' => $fromStatus->id,
            'to_status_id' => $toStatus->id,
            'roles_allowed' => $rolesAllowed,
            'requires_approval' => $requiresApproval,
            'requires_comment' => $requiresComment,
            'sla_hours' => $slaHours,
            'active' => true,
        ]);
    }
}
