<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentUploadDraftItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_upload_draft_id',
        'sort_order',
        'original_name',
        'stored_name',
        'temp_disk',
        'temp_path',
        'mime_type',
        'size_bytes',
        'title',
        'category_id',
        'status_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'size_bytes' => 'integer',
            'meta' => 'array',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(DocumentUploadDraft::class, 'document_upload_draft_id');
    }
}
