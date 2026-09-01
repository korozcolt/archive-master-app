<?php

namespace App\Models;

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\DocumentStatus;
use App\Enums\FinalDisposition;
use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\SlaStatus;
use App\Services\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Status|null $status
 * @property-read Company $company
 * @property-read Branch|null $branch
 * @property-read Department|null $department
 * @property-read Category|null $category
 * @property-read User $creator
 * @property-read User|null $assignee
 */
class Document extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /*
     * search() se renombra al importar el trait para poder envolverlo mas
     * abajo. Los metodos de un trait se aplanan dentro de la clase: sin este
     * alias, declarar search() lo reemplazaria por completo y parent::search()
     * apuntaria a Model, que no tiene ese metodo.
     */
    use Searchable {
        search as protected buscarConScout;
    }

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'category_id',
        'status_id',
        'created_by',
        'assigned_to',
        'sla_policy_id',
        'document_number',
        'barcode',
        'qrcode',
        'title',
        'description',
        'content',
        'file_path',
        'physical_location',
        'physical_location_id',
        'digital_document_type',
        'physical_document_type',
        'public_tracking_code',
        'tracking_enabled',
        'tracking_expires_at',
        'is_confidential',
        'is_archived',
        'priority',
        'pqrs_type',
        'legal_basis',
        'legal_term_days',
        'received_at',
        'due_date',
        'due_at',
        'sla_due_date',
        'sla_started_at',
        'sla_status',
        'sla_paused_at',
        'sla_pause_reason',
        'sla_resumed_at',
        'first_response_at',
        'closed_at',
        'sla_frozen_at',
        'escalated_at',
        'completed_at',
        'archived_at',
        'trd_series_id',
        'trd_subseries_id',
        'documentary_type_id',
        'access_level',
        'retention_management_years',
        'retention_central_years',
        'retention_historical_action',
        'final_disposition',
        'archive_phase',
        'archive_classification_code',
        'settings',
        'metadata',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'is_archived' => 'boolean',
        'tracking_enabled' => 'boolean',
        'priority' => Priority::class,
        'legal_term_days' => 'integer',
        'received_at' => 'datetime',
        'due_date' => 'datetime',
        'due_at' => 'datetime',
        'sla_due_date' => 'datetime',
        'sla_started_at' => 'datetime',
        'sla_status' => SlaStatus::class,
        'sla_paused_at' => 'datetime',
        'sla_resumed_at' => 'datetime',
        'first_response_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_frozen_at' => 'datetime',
        'escalated_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'access_level' => DocumentAccessLevel::class,
        'retention_management_years' => 'integer',
        'retention_central_years' => 'integer',
        'final_disposition' => FinalDisposition::class,
        'archive_phase' => ArchivePhase::class,
        'tracking_expires_at' => 'datetime',
        'settings' => 'json',
        'metadata' => 'json',
    ];

    public function getDueDateAttribute(): mixed
    {
        $value = $this->attributes['due_date'] ?? $this->attributes['due_at'] ?? null;

        if ($value === null) {
            return null;
        }

        return $this->asDateTime($value);
    }

    public function setDueDateAttribute(mixed $value): void
    {
        $this->attributes['due_date'] = $value;
        $this->attributes['due_at'] = $value;
    }

    public function setDueAtAttribute(mixed $value): void
    {
        $this->attributes['due_at'] = $value;
        $this->attributes['due_date'] = $value;
    }

    /**
     * Attributes that should not be persisted to the database
     */
    protected $hidden = [];

    /**
     * Temporary attributes used by observers
     */
    protected $attributes = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title', 'description', 'status_id', 'assigned_to',
                'is_confidential', 'is_archived', 'priority',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Status>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->assignee();
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function documentarySeries(): BelongsTo
    {
        return $this->belongsTo(DocumentarySeries::class, 'trd_series_id');
    }

    public function documentarySubseries(): BelongsTo
    {
        return $this->belongsTo(DocumentarySubseries::class, 'trd_subseries_id');
    }

    public function documentaryType(): BelongsTo
    {
        return $this->belongsTo(DocumentaryType::class);
    }

    public function slaEvents(): HasMany
    {
        return $this->hasMany(DocumentSlaEvent::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function workflowHistory(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function pendingApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class)->where('status', 'pending');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tags')
            ->using(DocumentTag::class)
            ->withTimestamps();
    }

    public function physicalLocation(): BelongsTo
    {
        return $this->belongsTo(PhysicalLocation::class, 'physical_location_id');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(DocumentLocationHistory::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(DocumentDistribution::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(DocumentAiRun::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(DocumentAccessRequest::class);
    }

    public function hasActiveAccessGrant(User $user): bool
    {
        return $this->accessRequests()
            ->where('requested_by', $user->id)
            ->where('status', 'approved')
            ->where('expires_at', '>', now())
            ->exists();
    }

    // Scopes
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

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeVisibleToPortalUser(Builder $query, User $user): Builder
    {
        return $query
            ->where('company_id', $user->company_id)
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);

                if ($user->hasRole(Role::Receptionist->value)) {
                    $builder->orWhereHas('receipts');
                }

                if ($user->hasRole(Role::RegularUser->value)) {
                    $builder->orWhereHas('receipts', function (Builder $receiptQuery) use ($user): void {
                        $receiptQuery->where('recipient_user_id', $user->id);
                    });
                }

                if (
                    $user->department_id &&
                    $user->hasAnyRole([Role::OfficeManager->value, Role::ArchiveManager->value])
                ) {
                    $builder->orWhereHas('distributions.targets', function (Builder $targetQuery) use ($user): void {
                        $targetQuery->where('department_id', $user->department_id);
                    });
                }

                if ($user->hasAnyRole([
                    Role::OfficeManager->value,
                    Role::ArchiveManager->value,
                    Role::ArchiveOperator->value,
                    Role::Admin->value,
                    Role::BranchAdmin->value,
                    Role::SuperAdmin->value,
                ])) {
                    $builder->orWhere(function (Builder $historicalQuery) use ($user): void {
                        $historicalQuery->where('metadata->entry_mode', 'historical');

                        if (! $this->hasHistoricalAccessWithoutRestriction($user)) {
                            $historicalQuery->whereIn('access_level', $this->historicalPortalAccessLevels($user));
                        }
                    });
                }

                if ($user->hasAnyRole([
                    Role::ArchiveManager->value,
                    Role::ArchiveOperator->value,
                    Role::Admin->value,
                    Role::BranchAdmin->value,
                    Role::SuperAdmin->value,
                ])) {
                    $builder->orWhereIn('archive_phase', [ArchivePhase::Central->value, ArchivePhase::Historico->value]);
                }

                $builder->orWhereHas('accessRequests', function (Builder $accessRequestQuery) use ($user): void {
                    $accessRequestQuery
                        ->where('requested_by', $user->id)
                        ->where('status', 'approved')
                        ->where('expires_at', '>', now());
                });
            });
    }

    public function canBeAccessedByPortalUser(User $user): bool
    {
        if ($this->company_id !== $user->company_id) {
            return false;
        }

        return $this->hasImplicitPortalAccess($user) || $this->hasActiveAccessGrant($user);
    }

    public function hasImplicitPortalAccess(User $user): bool
    {
        if ($this->isHistoricalEntry()) {
            return $this->canBeAccessedAsHistoricalBy($user);
        }

        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        if ($user->hasRole(Role::BranchAdmin->value)) {
            return $this->branch_id === null || $this->branch_id === $user->branch_id;
        }

        if ($user->hasRole(Role::OfficeManager->value)) {
            return $this->department_id === $user->department_id
                || ($user->department_id && $this->distributions()
                    ->whereHas('targets', fn (Builder $query) => $query->where('department_id', $user->department_id))
                    ->exists());
        }

        if ($user->hasRole(Role::ArchiveManager->value)) {
            return true;
        }

        if ($user->hasRole(Role::ArchiveOperator->value)) {
            return $this->created_by === $user->id
                || $this->isHistoricalEntry()
                || in_array($this->archive_phase, [ArchivePhase::Central, ArchivePhase::Historico], true);
        }

        if ($user->hasRole(Role::Receptionist->value) && $this->receipts()->exists()) {
            return true;
        }

        return $this->created_by === $user->id
            || $this->assigned_to === $user->id
            || ($user->hasRole(Role::RegularUser->value) && $this->receipts()
                ->where('recipient_user_id', $user->id)
                ->exists());
    }

    public function canBeAccessedAsHistoricalBy(User $user): bool
    {
        if (! $this->isHistoricalEntry() || $this->company_id !== $user->company_id) {
            return false;
        }

        if ($this->hasHistoricalAccessWithoutRestriction($user)) {
            return true;
        }

        if (! $user->hasRole(Role::OfficeManager->value)) {
            return false;
        }

        return in_array($this->access_level?->value, $this->historicalPortalAccessLevels($user), true);
    }

    protected function historicalPortalAccessLevels(User $user): array
    {
        if ($user->hasRole(Role::OfficeManager->value)) {
            return [
                DocumentAccessLevel::Publico->value,
                DocumentAccessLevel::Interno->value,
            ];
        }

        return [];
    }

    protected function hasHistoricalAccessWithoutRestriction(User $user): bool
    {
        return $user->hasAnyRole([
            Role::ArchiveManager->value,
            Role::ArchiveOperator->value,
            Role::Admin->value,
            Role::BranchAdmin->value,
            Role::SuperAdmin->value,
        ]);
    }

    public function scopeConfidential($query)
    {
        return $query->where('is_confidential', true);
    }

    public function scopeNotConfidential($query)
    {
        return $query->where('is_confidential', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByPqrsType($query, string $pqrsType)
    {
        return $query->where('pqrs_type', $pqrsType);
    }

    public function scopeWithActiveSla($query)
    {
        return $query
            ->whereNotNull('sla_due_date')
            ->whereNotIn('sla_status', [SlaStatus::Closed->value, SlaStatus::Frozen->value]);
    }

    public function scopeByArchivePhase($query, ArchivePhase|string $phase)
    {
        return $query->where('archive_phase', $phase instanceof ArchivePhase ? $phase->value : $phase);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_at')
            ->whereNull('completed_at')
            ->where('due_at', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereNotNull('due_at')
            ->whereNull('completed_at')
            ->whereDate('due_at', now()->toDateString());
    }

    public function scopeDueSoon($query, $days = 3)
    {
        return $query->whereNotNull('due_at')
            ->whereNull('completed_at')
            ->whereBetween('due_at', [now()->addDay(), now()->addDays($days)]);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function (Builder $query) use ($search) {
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%")
                ->orWhereHas('category', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('status', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function scopeDigitalOriginal($query)
    {
        return $query->where('digital_document_type', 'original');
    }

    public function scopeDigitalCopy($query)
    {
        return $query->where('digital_document_type', 'copia');
    }

    public function scopePhysicalOriginal($query)
    {
        return $query->where('physical_document_type', 'original');
    }

    public function scopePhysicalCopy($query)
    {
        return $query->where('physical_document_type', 'copia');
    }

    public function scopeNoPhysical($query)
    {
        return $query->where('physical_document_type', 'no_aplica');
    }

    public function scopeWithTracking($query)
    {
        return $query->whereNotNull('public_tracking_code');
    }

    public function scopeTrackingActive($query)
    {
        return $query->where('tracking_enabled', true)
            ->where(function ($q) {
                $q->whereNull('tracking_expires_at')
                    ->orWhere('tracking_expires_at', '>', now());
            });
    }

    public function scopeInPhysicalLocation($query, $locationId)
    {
        return $query->where('physical_location_id', $locationId);
    }

    // Accessors
    public function getCurrentVersionAttribute(): ?DocumentVersion
    {
        return $this->versions()->latest()->first();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->name ?? 'Sin estado';
    }

    public function getPriorityLabelAttribute(): string
    {
        if (! $this->priority) {
            return 'Normal';
        }

        try {
            return $this->priority->getLabel();
        } catch (\ValueError $e) {
            return ucfirst($this->priority?->value ?? $this->priority);
        }
    }

    public function getCategoryNameAttribute(): string
    {
        return $this->category?->name ?? 'Sin categoría';
    }

    public function getIsSlaFrozenAttribute(): bool
    {
        return $this->sla_status === SlaStatus::Frozen || $this->sla_frozen_at !== null;
    }

    // Métodos

    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_at) {
            return null;
        }

        return now()->diffInDays($this->due_at, false);
    }

    public function getTimeElapsedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function addVersion(?string $content = null, ?string $filePath = null, ?User $user = null): DocumentVersion
    {
        // Obtener el último número de versión y aumentarlo en 1
        $versionNumber = $this->versions()->max('version_number') + 1;

        // Si hay versiones previas, establecerlas como no actuales
        if ($versionNumber > 1) {
            $this->versions()->update(['is_current' => false]);
        }

        // Crear la nueva versión
        return $this->versions()->create([
            'created_by' => $user ? $user->id : ($this->created_by ?? Auth::id()),
            'version_number' => $versionNumber,
            'content' => $content ?? $this->content,
            'file_path' => $filePath,
            'is_current' => true,
            'change_summary' => "Versión {$versionNumber} creada",
        ]);
    }

    public function changeStatus(Status $newStatus, ?User $user = null, ?string $comments = null): bool
    {
        $currentStatusId = $this->status_id;

        // Actualizar el estado del documento
        $this->status_id = $newStatus->id;
        $success = $this->save();

        // Registrar el cambio en el historial de workflow
        if ($success) {
            WorkflowHistory::create([
                'document_id' => $this->id,
                'performed_by' => $user ? $user->id : Auth::id(),
                'from_status_id' => $currentStatusId,
                'to_status_id' => $newStatus->id,
                'comments' => $comments,
            ]);
        }

        return $success;
    }

    public function archive(?User $user = null, ?string $comments = null): bool
    {
        $this->is_archived = true;
        $this->archived_at = now();

        $success = $this->save();

        // Si el documento tenía un estado, registramos el cambio a "Archivado"
        if ($success && $this->status_id) {
            // Buscar o crear el estado "Archivado"
            $archivedStatus = Status::firstOrCreate(
                [
                    'company_id' => $this->company_id,
                    'slug' => DocumentStatus::Archived->value,
                ],
                [
                    'name' => DocumentStatus::Archived->getLabel(),
                    'color' => DocumentStatus::Archived->getColor(),
                    'icon' => DocumentStatus::Archived->getIcon(),
                    'is_final' => true,
                    'active' => true,
                ]
            );

            $this->changeStatus($archivedStatus, $user, $comments ?? 'Documento archivado');
        }

        return $success;
    }

    public function unarchive(?User $user = null, ?string $comments = null): bool
    {
        $this->is_archived = false;
        $this->archived_at = null;

        return $this->save();
    }

    public function complete(?User $user = null, ?string $comments = null): bool
    {
        $this->completed_at = now();

        $success = $this->save();

        // Registrar el cambio en el historial de workflow si es necesario
        if ($success && $this->status_id) {
            // Buscar o crear el estado "Aprobado"
            $approvedStatus = Status::firstOrCreate(
                [
                    'company_id' => $this->company_id,
                    'slug' => DocumentStatus::Approved->value,
                ],
                [
                    'name' => DocumentStatus::Approved->getLabel(),
                    'color' => DocumentStatus::Approved->getColor(),
                    'icon' => DocumentStatus::Approved->getIcon(),
                    'is_final' => true,
                    'active' => true,
                ]
            );

            $this->changeStatus($approvedStatus, $user, $comments ?? 'Documento completado');
        }

        return $success;
    }

    public function generateDocumentNumber(): string
    {
        $prefix = 'DOC';
        // Handle translatable company name
        $companyName = $this->company->name;
        if (is_array($companyName)) {
            $companyName = $this->company->getTranslation('name', app()->getLocale());
        }

        $normalizedCompanyName = Str::upper(Str::ascii((string) $companyName));
        $lettersOnly = preg_replace('/[^A-Z0-9]/', '', $normalizedCompanyName) ?: 'EMP';
        $companyCode = substr($lettersOnly, 0, 3);

        if (strlen($companyCode) < 3) {
            $companyCode = str_pad($companyCode, 3, 'X');
        }

        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));

        return "{$prefix}-{$companyCode}-{$timestamp}-{$random}";
    }

    public function generateBarcode(): string
    {
        // Usar document_number como base y eliminar guiones y caracteres especiales
        $base = preg_replace('/[^A-Za-z0-9]/', '', $this->document_number);
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        return "{$base}{$random}";
    }

    public function generateQRCode(): string
    {
        // Crear un JSON con la información importante del documento
        $companyName = $this->company->name;
        // Handle translatable attribute
        if (is_array($companyName)) {
            $companyName = $this->company->getTranslation('name', app()->getLocale());
        }

        $data = [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'company' => $companyName,
            'created_at' => $this->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
        ];

        return json_encode($data);
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }

    /**
     * Transicionar el documento a un nuevo estado
     */
    public function transitionTo(Status $newStatus, ?string $comment = null, ?User $user = null): bool
    {
        $workflowEngine = app(WorkflowEngine::class);

        return $workflowEngine->transitionDocument($this, $newStatus, $comment, $user);
    }

    /**
     * Obtener transiciones disponibles para el usuario actual
     */
    public function getAvailableTransitions(?User $user = null): array
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return [];
        }

        $workflowEngine = app(WorkflowEngine::class);

        return $workflowEngine->getAvailableTransitions($this, $user);
    }

    /**
     * Verificar si puede transicionar a un estado específico
     */
    public function canTransitionTo(Status $newStatus, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        $workflowEngine = app(WorkflowEngine::class);

        return $workflowEngine->canTransition($this, $newStatus, $user);
    }

    // Hooks
    protected static function booted()
    {
        static::creating(function (Document $document) {
            // Generar document_number, barcode y qrcode automáticamente si no se proporcionan
            if (empty($document->document_number)) {
                $document->document_number = $document->generateDocumentNumber();
            }

            if (empty($document->barcode)) {
                $document->barcode = $document->generateBarcode();
            }

            if (empty($document->qrcode)) {
                $document->qrcode = $document->generateQRCode();
            }

            // Establecer la fecha de recepción si no se proporciona
            if (empty($document->received_at)) {
                $document->received_at = now();
            }

            // Establecer el creador si no se proporciona
            if (empty($document->created_by) && Auth::check()) {
                $document->created_by = Auth::id();
            }
        });

        static::created(function (Document $document) {
            // Crear la primera versión del documento
            $document->addVersion();
        });
    }

    /**
     * Get the indexable data array for the model.
     */
    public function makeAllSearchableUsing($query)
    {
        return $query->with(['company', 'branch', 'department', 'category', 'status', 'creator', 'assignee', 'tags']);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'category_id' => $this->category_id,
            'status_id' => $this->status_id,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'archive_phase' => $this->archive_phase?->value,
            'document_number' => $this->document_number,
            'barcode' => $this->barcode,
            'qrcode' => $this->qrcode,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'historical_department_name' => $this->metadata['historical']['original_department_name'] ?? null,
            'historical_reference_code' => $this->metadata['historical']['reference_code'] ?? null,
            'historical_box' => $this->metadata['historical']['box'] ?? null,
            'historical_folder' => $this->metadata['historical']['folder'] ?? null,
            'historical_volume' => $this->metadata['historical']['volume'] ?? null,
            'historical_keywords_text' => $this->metadata['historical']['keywords_text'] ?? null,
            'physical_location' => $this->physical_location,
            'priority' => $this->priority?->value,
            'is_confidential' => $this->is_confidential,
            'is_archived' => $this->is_archived,
            'company_name' => $this->company?->name,
            'branch_name' => $this->branch?->name,
            'department_name' => $this->department?->name,
            'category_name' => $this->category?->name,
            'status_name' => $this->status?->name,
            'creator_name' => $this->creator?->name,
            'assignee_name' => $this->assignee?->name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
            'received_at' => $this->received_at?->timestamp,
            'due_date' => $this->due_date?->timestamp,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'documents';
    }

    /**
     * Tope de documentos que se recuperan del indice para acotar por titulo.
     *
     * Coincide con el maxTotalHits por defecto de Meilisearch: pedir mas no
     * traeria nada adicional.
     */
    private const TOPE_EXPEDIENTE = 1000;

    /**
     * Cualquier termino unido por guiones se trata como una unidad.
     *
     * Cubre las formas del archivo -LP-ADS-001-2024, G100-1395-2024, R-2464,
     * DOC-AGU-20260713094648-5265- y tambien palabras compuestas como
     * "pre-contractual". No se exige que lleve digitos a proposito: quien
     * escribe un guion esta escribiendo una sola cosa, y partirla en pedazos
     * es justamente el defecto que se corrige aqui.
     */
    private const PATRON_IDENTIFICADOR = '/^[\p{L}\d]+(?:-[\p{L}\d]+)+$/u';

    /**
     * Buscar documentos.
     *
     * Se sobrescribe aqui, y no en cada punto de llamada, porque hay siete
     * repartidos entre el portal, la API, la busqueda avanzada y el panel:
     * poniendolo en el modelo, cualquier busqueda futura lo hereda sin que
     * nadie tenga que acordarse.
     *
     * Hay dos comportamientos, y cual se aplica depende de si el usuario
     * escribio un identificador:
     *
     * 1. Sin identificador se mantiene la estrategia "frequency". Meilisearch
     *    descarta terminos cuando no hay resultados para todos, y su estrategia
     *    por defecto ("last") descarta el ultimo que escribio el usuario, que
     *    suele ser justo el que anadio para afinar: "factura aguas 2026"
     *    devolveria todas las facturas de aguas de cualquier ano. Con
     *    "frequency" descarta el mas comun -"aguas"- y conserva el especifico.
     *
     * 2. Con identificador se busca como frase exacta. Sin comillas Meilisearch
     *    parte LP-ADS-001-2024 en LP + ADS + 001 + 2024, y como esas piezas
     *    salen en miles de documentos, cualquier numero arrastraba medio
     *    archivo: se midio que devolvia mas de 1000 resultados donde solo habia
     *    186 documentos con ese numero. Entre comillas la busqueda es exacta;
     *    se verificaron 250 resultados de tres licitaciones sin un solo falso
     *    positivo.
     *
     *    Las palabras sueltas que acompanen al identificador se aplican **solo
     *    contra el titulo**. Buscarlas en el texto completo no distingue nada:
     *    todos los documentos de un expediente de contratacion mencionan la
     *    palabra "contrato" en su contenido, asi que exigirla no filtraba. En
     *    el titulo si: de los 186 documentos de LP-ADS-001-2024, dos lo llevan
     *    en el titulo y uno de ellos es el contrato.
     *
     * @param  string  $query
     * @param  callable|null  $callback
     */
    public static function search($query = '', $callback = null): \Laravel\Scout\Builder
    {
        $consulta = static::normalizarConsulta((string) $query);

        [$anclaje, $exigenciasDeTitulo] = static::analizarConsulta($consulta);

        if ($anclaje === '') {
            return static::buscarConScout($consulta, $callback)
                ->options(['matchingStrategy' => 'frequency']);
        }

        $busqueda = static::buscarConScout($anclaje, $callback)
            ->options(['matchingStrategy' => 'all']);

        if ($exigenciasDeTitulo === []) {
            return $busqueda;
        }

        return $busqueda->whereIn('id', static::idsConTitulosQueCumplen($anclaje, $exigenciasDeTitulo));
    }

    /**
     * Reconstruir el identificador que el usuario quiso escribir.
     *
     * Nadie teclea "UO-PSPR-ADS-001-2020". Teclea lo que ve en el papel, que
     * lleva "No.", espacios donde no van y guiones sueltos al final:
     *
     *   UO-PSPR-ADS- No. 001-2020      ->  0 resultados
     *   UO-PSPR-ADS No 001 2020        ->  0 resultados
     *   UO-PSPR-ADS-001-2020           ->  6 resultados, los correctos
     *
     * El documento existe; solo hay que entender lo que le pidieron. Y no basta
     * con partir la consulta y buscar los trozos por separado, porque sueltos no
     * distinguen nada: en este archivo "001" devuelve mas de mil documentos y
     * "2020" otros tantos, mientras que el identificador entero devuelve seis.
     * Por eso se reengancha en vez de trocear.
     *
     * El ruido ordinal solo se quita cuando va seguido de digitos. Sin esa
     * condicion, buscar un documento titulado "NUMERO DE RADICADO" se quedaria
     * en "de radicado".
     *
     * Las consultas bien escritas pasan intactas: "acta de inicio" y
     * "LP-ADS-001-2024, CONTRATO" salen tal cual entraron.
     */
    private static function normalizarConsulta(string $consulta): string
    {
        $texto = trim($consulta);

        if ($texto === '') {
            return '';
        }

        // Sin un guion no hay identificador que rearmar, y tocar la consulta
        // solo hace dano. Se midio con "COMUNICACION INTERNA N°0069": quitando
        // el ordinal, "0069" queda como numero suelto, la tolerancia a erratas
        // lo confunde con 0093, 0089 y 0098, y el documento correcto desaparece
        // entre 232 resultados. Dejandola intacta, Meilisearch la tokeniza igual
        // que al titulo. Los ordinales se resuelven al comparar con el titulo,
        // donde se normalizan los dos lados a la vez.
        if (! str_contains($texto, '-')) {
            return preg_replace('/\s+/', ' ', $texto) ?? $texto;
        }

        // "No." / "N°" / "Nº" / "num." / "numero" / "#", solo ante digitos.
        $texto = preg_replace('/\b(?:n[oº°]\.?|n\.|n[uú]m(?:ero)?\.?)\s*(?=\d)/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/#\s*(?=\d)/', '', $texto) ?? $texto;

        // "UO - PSPR - ADS" y "UO-PSPR-ADS- 001" acaban pegados.
        $texto = preg_replace('/\s*-\s*/', '-', $texto) ?? $texto;
        $texto = preg_replace('/-\s+/', '-', $texto) ?? $texto;

        // Un numero suelto detras de un identificador le pertenece:
        // "UO-PSPR-ADS 001 2020" -> "UO-PSPR-ADS-001-2020".
        $piezas = [];

        foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $pieza) {
            $anterior = $piezas !== [] ? $piezas[count($piezas) - 1] : null;

            if (preg_match('/^\d+$/', $pieza) === 1
                && $anterior !== null
                && preg_match(self::PATRON_IDENTIFICADOR, $anterior) === 1) {
                $piezas[count($piezas) - 1] = $anterior.'-'.$pieza;

                continue;
            }

            $piezas[] = $pieza;
        }

        return trim(implode(' ', $piezas));
    }

    /**
     * Descomponer lo que escribio el usuario en dos partes.
     *
     * Devuelve la consulta que se manda al indice -el "anclaje"- y la lista de
     * exigencias que ademas debe cumplir el titulo. Cadena vacia como anclaje
     * significa que no hay nada especial que hacer y se sigue el camino de
     * siempre.
     *
     * La coma separa unidades, y todas deben cumplirse. Es la unica sintaxis
     * que se ofrece, a proposito: se eligio frente a comillas u operadores
     * porque no hay nada que aprender, se escribe igual que una lista y en un
     * teclado espanol sale sin combinaciones raras. Quien no ponga comas no
     * nota ningun cambio.
     *
     *   LP-2105-2024, ACTA DE INICIO
     *     -> anclaje "LP-2105-2024", el titulo debe contener "ACTA DE INICIO"
     *        completo y en ese orden.
     *
     *   LP-2105-2024 ACTA DE INICIO
     *     -> anclaje "LP-2105-2024", el titulo debe contener ACTA, DE e INICIO
     *        sueltas, en cualquier orden. Menos preciso, pero se conserva
     *        porque nadie deberia verse obligado a aprender la coma.
     *
     *   ACTA DE INICIO, 2024
     *     -> sin identificador, el primer segmento va al indice y el resto
     *        exige al titulo. La coma sigue significando lo mismo.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function analizarConsulta(string $consulta): array
    {
        if (str_contains($consulta, ',')) {
            $segmentos = collect(explode(',', $consulta))
                // El usuario que ya entrecomilla pide exactitud a mano: se
                // respeta su intencion quitando solo las comillas sobrantes.
                ->map(fn (string $segmento): string => trim($segmento, " \t\n\r\0\x0B\"'"))
                ->filter(fn (string $segmento): bool => $segmento !== '')
                ->values();

            if ($segmentos->isEmpty()) {
                return ['', []];
            }

            $identificadores = $segmentos->filter(
                fn (string $s): bool => preg_match(self::PATRON_IDENTIFICADOR, $s) === 1
            );

            // Ancla el identificador si lo hay; si no, el primer segmento.
            // Lo demas queda como exigencia sobre el titulo.
            $anclajes = $identificadores->isNotEmpty() ? $identificadores : $segmentos->take(1);

            return [
                $anclajes->map(fn (string $s): string => '"'.$s.'"')->implode(' '),
                $segmentos->reject(fn (string $s): bool => $anclajes->contains($s))->values()->all(),
            ];
        }

        $identificadores = [];
        $palabras = [];

        foreach (preg_split('/\s+/u', $consulta, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $termino) {
            $limpio = trim($termino, '"\'');

            if ($limpio === '') {
                continue;
            }

            if (preg_match(self::PATRON_IDENTIFICADOR, $limpio) === 1) {
                $identificadores[] = $limpio;

                continue;
            }

            $palabras[] = $limpio;
        }

        if ($identificadores === []) {
            return ['', []];
        }

        return [
            collect($identificadores)->map(fn (string $id): string => '"'.$id.'"')->implode(' '),
            $palabras,
        ];
    }

    /**
     * Conectores que no aportan nada al comparar con un titulo.
     *
     * Un usuario pidio "ACTA DE SUSPENSION N°1" y el documento se titula "ACTA
     * SUSPENSION N°1", sin el "DE". Exigir cada palabra dejaba fuera el
     * documento correcto por una preposicion.
     */
    private const CONECTORES = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'en', 'a', 'al', 'con', 'para', 'por'];

    /**
     * De los documentos que devolvio el anclaje, cuales cumplen en el titulo.
     *
     * La comparacion se hace sobre una forma normalizada de **ambos lados**, no
     * del titulo crudo. Es lo que evita dos fallos que se vieron en uso real:
     *
     *   - "OTROSI N°4" no encontraba el documento titulado "OTROSI N°4". La
     *     consulta llegaba aqui ya sin el "N°" y el titulo si lo tenia, con lo
     *     que "otrosi 4" no aparecia dentro de "OTROSI N°4". Normalizando los
     *     dos lados, ambos quedan en "otrosi 4".
     *   - "ACTA DE SUSPENSION N°1" no encontraba "ACTA SUSPENSION N°1" por una
     *     preposicion de mas. Los conectores se descartan.
     *
     * Se compara en PHP y no con un LIKE en SQL porque normalizar acentos,
     * ordinales y puntuacion dentro de la consulta seria una expresion enorme y
     * distinta en MySQL y en SQLite. Los candidatos son los que ya devolvio el
     * indice -cientos como mucho-, asi que traer su titulo no cuesta nada y
     * ademas evita el LIKE con comodin inicial sobre `documents`.
     *
     * @param  list<string>  $exigencias
     * @return list<int>
     */
    private static function idsConTitulosQueCumplen(string $anclaje, array $exigencias): array
    {
        $candidatos = static::buscarConScout($anclaje)
            ->options(['matchingStrategy' => 'all'])
            ->take(self::TOPE_EXPEDIENTE)
            ->keys();

        if ($candidatos->isEmpty()) {
            return [];
        }

        $buscadas = collect($exigencias)
            ->flatMap(fn (string $exigencia): array => static::palabrasSignificativas($exigencia))
            ->unique()
            ->values();

        if ($buscadas->isEmpty()) {
            return $candidatos->all();
        }

        return static::query()
            ->whereKey($candidatos)
            ->pluck('title', 'id')
            ->filter(function (?string $titulo) use ($buscadas): bool {
                $normalizado = static::formaComparable((string) $titulo);

                return $buscadas->every(fn (string $palabra): bool => str_contains($normalizado, $palabra));
            })
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Palabras que de verdad discriminan dentro de una exigencia de titulo.
     *
     * @return list<string>
     */
    private static function palabrasSignificativas(string $texto): array
    {
        $palabras = preg_split('/\s+/u', static::formaComparable($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $utiles = array_values(array_filter(
            $palabras,
            fn (string $palabra): bool => ! in_array($palabra, self::CONECTORES, true)
        ));

        // Si la exigencia era solo conectores -alguien buscando "de la"- se
        // conservan: mejor exigir algo raro que no exigir nada.
        return $utiles !== [] ? $utiles : $palabras;
    }

    /**
     * Reducir un texto a lo comparable: sin acentos, ordinales ni puntuacion.
     */
    private static function formaComparable(string $texto): string
    {
        $t = mb_strtolower(trim($texto));

        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        // "n°4", "no. 4", "num 4" y "#4" son la misma cosa que "4".
        $t = preg_replace('/\b(?:n[oº°]\.?|n\.|n[uú]m(?:ero)?\.?)\s*(?=\d)/u', '', $t) ?? $t;

        // Cualquier otro signo separa palabras en vez de pegarlas.
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;

        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed();
    }

    /**
     * Verificar si el documento tiene todas las firmas requeridas (para contratos)
     */
    public function hasRequiredSignatures(): bool
    {
        // Implementar lógica para verificar firmas
        // Por ahora retorna true, pero se puede extender con un sistema de firmas
        return true;
    }

    /**
     * Verificar si el documento tiene comprobante de pago (para facturas)
     */
    public function hasPaymentProof(): bool
    {
        // Implementar lógica para verificar comprobante de pago
        // Por ahora retorna true, pero se puede extender con un sistema de pagos
        return true;
    }

    /**
     * Verificar si el documento tiene las aprobaciones requeridas (para reportes)
     */
    public function hasRequiredApprovals(): bool
    {
        // Implementar lógica para verificar aprobaciones
        // Por ahora retorna true, pero se puede extender con un sistema de aprobaciones
        return true;
    }

    /**
     * Obtener el supervisor del usuario asignado
     */
    public function getSupervisor(): ?User
    {
        if ($this->assignee && method_exists($this->assignee, 'supervisor')) {
            return $this->assignee->supervisor;
        }

        return null;
    }

    /**
     * Verificar si el documento está vencido según SLA
     */
    public function isOverdue(): bool
    {
        if (! $this->status || $this->status->is_final) {
            return false;
        }

        $workflowDefinition = $this->status->fromWorkflows()
            ->where('company_id', $this->company_id)
            ->first();

        if ($workflowDefinition && $workflowDefinition->sla_hours) {
            $slaDeadline = $this->created_at->addHours($workflowDefinition->sla_hours);

            return now()->gt($slaDeadline);
        }

        return false;
    }

    /**
     * Obtener tiempo restante para SLA
     */
    public function getTimeToSLA(): ?int
    {
        if (! $this->status || $this->status->is_final) {
            return null;
        }

        $workflowDefinition = $this->status->fromWorkflows()
            ->where('company_id', $this->company_id)
            ->first();

        if ($workflowDefinition && $workflowDefinition->sla_hours) {
            $slaDeadline = $this->created_at->addHours($workflowDefinition->sla_hours);
            $hoursRemaining = now()->diffInHours($slaDeadline, false);

            return $hoursRemaining > 0 ? $hoursRemaining : 0;
        }

        return null;
    }

    /**
     * Generar código único para tracking público
     */
    public function generatePublicTrackingCode(): string
    {
        return strtoupper(substr(md5($this->id.$this->document_number.uniqid()), 0, 32));
    }

    /**
     * Habilitar tracking público del documento
     */
    public function enableTracking(?int $expiresInDays = null): bool
    {
        if (empty($this->public_tracking_code)) {
            $this->public_tracking_code = $this->generatePublicTrackingCode();
        }

        $this->tracking_enabled = true;

        if ($expiresInDays !== null) {
            $this->tracking_expires_at = now()->addDays($expiresInDays);
        }

        return $this->save();
    }

    /**
     * Deshabilitar tracking público del documento
     */
    public function disableTracking(): bool
    {
        $this->tracking_enabled = false;

        return $this->save();
    }

    /**
     * Verificar si el tracking está activo
     */
    public function isTrackingActive(): bool
    {
        if (! $this->tracking_enabled || empty($this->public_tracking_code)) {
            return false;
        }

        if ($this->tracking_expires_at && $this->tracking_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Mover documento a una nueva ubicación física
     */
    public function moveToLocation(
        PhysicalLocation $newLocation,
        ?string $notes = null,
        ?User $movedBy = null,
        string $movementType = 'moved'
    ): bool {
        return DB::transaction(function () use ($newLocation, $notes, $movedBy, $movementType): bool {
            $oldLocationId = $this->physical_location_id;
            $newLocationId = $newLocation->getKey();
            $isSameLocation = $oldLocationId && (int) $oldLocationId === (int) $newLocationId;

            if (! $isSameLocation && ! $newLocation->incrementCapacity()) {
                return false;
            }

            $this->physical_location_id = $newLocationId;

            if (! $this->saveQuietly()) {
                if (! $isSameLocation) {
                    $newLocation->decrementCapacity();
                }

                return false;
            }

            DocumentLocationHistory::create([
                'document_id' => $this->id,
                'physical_location_id' => $newLocationId,
                'moved_from_location_id' => $oldLocationId,
                'moved_by' => $movedBy?->id ?? Auth::id(),
                'movement_type' => $movementType,
                'notes' => $notes,
                'moved_at' => now(),
            ]);

            if ($oldLocationId && ! $isSameLocation) {
                $oldLocation = PhysicalLocation::find($oldLocationId);
                $oldLocation?->decrementCapacity();
            }

            return true;
        });
    }

    /**
     * Obtener historial de movimientos de ubicación
     */
    public function getLocationHistory(?int $limit = null)
    {
        $query = $this->locationHistory()->orderBy('moved_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Obtener la ubicación actual formateada
     */
    public function getCurrentLocationPath(): ?string
    {
        return $this->physicalLocation?->full_path;
    }

    /**
     * Verificar si tiene ubicación física asignada
     */
    public function hasPhysicalLocation(): bool
    {
        return $this->physical_location_id !== null;
    }

    public function isHistoricalEntry(): bool
    {
        return data_get($this->metadata, 'entry_mode') === 'historical';
    }
}
