<?php

namespace App\Models;

use App\Enums\Role as AppRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, Searchable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'branch_id',
        'department_id',
        'position',
        'phone',
        'profile_photo',
        'language',
        'timezone',
        'settings',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'company_id', 'branch_id', 'department_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return $this->hasAnyRole([
            AppRole::SuperAdmin->value,
            AppRole::Admin->value,
            AppRole::BranchAdmin->value,
        ]);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->createdDocuments();
    }

    public function assignedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'assigned_to');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'created_by');
    }

    public function workflowHistory(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'performed_by');
    }

    public function issuedReceipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'issued_by');
    }

    public function receivedReceipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'recipient_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles) && strpos($roles, '|') !== false) {
            $roles = explode('|', $roles);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return $this->roles->contains('name', $roles);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'position' => $this->position,
            'phone' => $this->phone,
            'language' => $this->language,
            'company_name' => $this->company?->name,
            'branch_name' => $this->branch?->name,
            'department_name' => $this->department?->name,
            'roles' => $this->roles->pluck('name')->toArray(),
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'archivemasterusers';
    }
}
