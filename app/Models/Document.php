<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'category_id',
        'status_id',
        'created_by',
        'assigned_to',
        'document_number',
        'barcode',
        'qrcode',
        'title',
        'description',
        'content',
        'physical_location',
        'is_confidential',
        'is_archived',
        'priority',
        'received_at',
        'due_at',
        'completed_at',
        'archived_at',
        'settings',
        'metadata',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'is_archived' => 'boolean',
        'received_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'settings' => 'json',
        'metadata' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title', 'description', 'status_id', 'assigned_to',
                'is_confidential', 'is_archived', 'priority'
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

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function workflowHistory(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tags')
            ->using(DocumentTag::class)
            ->withTimestamps();
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

    // Accessors
    public function getCurrentVersionAttribute()
    {
        return $this->versions()->latest()->first();
    }

    public function getStatusLabelAttribute()
    {
        return $this->status?->name ?? 'Sin estado';
    }

    public function getPriorityLabelAttribute()
    {
        if (!$this->priority) {
            return 'Normal';
        }

        try {
            return Priority::from($this->priority)->getLabel();
        } catch (\ValueError $e) {
            return ucfirst($this->priority);
        }
    }

    public function getCategoryNameAttribute()
    {
        return $this->category?->name ?? 'Sin categoría';
    }

    // Métodos
    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at < now() && !$this->completed_at;
    }

    public function getDaysUntilDueAttribute()
    {
        if (!$this->due_at) {
            return null;
        }

        return now()->diffInDays($this->due_at, false);
    }

    public function getTimeElapsedAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function addVersion(string $content = null, string $filePath = null, User $user = null): DocumentVersion
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

    public function changeStatus(Status $newStatus, User $user = null, string $comments = null): bool
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

    public function archive(User $user = null, string $comments = null): bool
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
                    'slug' => DocumentStatus::Archived->value
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

    public function unarchive(User $user = null, string $comments = null): bool
    {
        $this->is_archived = false;
        $this->archived_at = null;

        return $this->save();
    }

    public function complete(User $user = null, string $comments = null): bool
    {
        $this->completed_at = now();

        $success = $this->save();

        // Registrar el cambio en el historial de workflow si es necesario
        if ($success && $this->status_id) {
            // Buscar o crear el estado "Aprobado"
            $approvedStatus = Status::firstOrCreate(
                [
                    'company_id' => $this->company_id,
                    'slug' => DocumentStatus::Approved->value
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
        $companyCode = strtoupper(substr($this->company->name, 0, 3));
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
        $data = [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'company' => $this->company->name,
            'created_at' => $this->created_at->toDateTimeString()
        ];

        return json_encode($data);
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
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
}
