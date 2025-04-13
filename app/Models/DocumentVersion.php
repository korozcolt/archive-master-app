<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentVersion extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'document_id',
        'created_by',
        'version_number',
        'content',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'is_current',
        'change_summary',
        'metadata',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'metadata' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['version_number', 'is_current', 'change_summary'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeLatestVersions($query)
    {
        return $query->orderBy('document_id')->orderBy('version_number', 'desc');
    }

    public function scopeWithFiles($query)
    {
        return $query->whereNotNull('file_path');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }

        return null;
    }

    public function getFileExtensionAttribute()
    {
        if ($this->file_name) {
            return pathinfo($this->file_name, PATHINFO_EXTENSION);
        }

        return null;
    }

    public function getHumanFileSizeAttribute()
    {
        if (!$this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getVersionNameAttribute()
    {
        return "V{$this->version_number}" . ($this->is_current ? ' (Actual)' : '');
    }

    public function getCreatedByNameAttribute()
    {
        return $this->creator?->name ?? 'Sistema';
    }

    // Métodos
    public function markAsCurrent(): bool
    {
        // Primero, desmarcamos la versión actual
        $this->document->versions()->update(['is_current' => false]);

        // Luego, marcamos esta versión como actual
        $this->is_current = true;

        return $this->save();
    }

    public function compare(DocumentVersion $otherVersion): array
    {
        // Implementación básica de comparación, puede mejorarse con librerías como diff
        $diff = [];

        if ($this->content != $otherVersion->content) {
            $diff['content'] = [
                'old' => $otherVersion->content,
                'new' => $this->content
            ];
        }

        if ($this->file_path != $otherVersion->file_path) {
            $diff['file'] = [
                'old' => $otherVersion->file_name,
                'new' => $this->file_name
            ];
        }

        return $diff;
    }

    // Hooks
    protected static function booted()
    {
        static::creating(function (DocumentVersion $version) {
            // Si no se proporciona metadata, inicializar como array vacío
            if (empty($version->metadata)) {
                $version->metadata = [];
            }

            // Añadir información del creador a los metadatos
            if ($version->created_by) {
                $version->metadata = array_merge($version->metadata, [
                    'created_by_name' => User::find($version->created_by)?->name ?? 'Usuario desconocido',
                    'created_at_formatted' => now()->format('d/m/Y H:i:s')
                ]);
            }

            // Si no se proporciona change_summary, generar uno automáticamente
            if (empty($version->change_summary)) {
                $previousVersion = DocumentVersion::where('document_id', $version->document_id)
                    ->where('version_number', '<', $version->version_number)
                    ->orderBy('version_number', 'desc')
                    ->first();

                if ($previousVersion) {
                    $version->change_summary = "Actualización desde versión {$previousVersion->version_number}";
                } else {
                    $version->change_summary = "Versión inicial";
                }
            }
        });
    }
}
