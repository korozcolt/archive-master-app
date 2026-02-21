<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAiOutput extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentAiOutputFactory> */
    use HasFactory;

    protected $fillable = [
        'document_ai_run_id',
        'summary_md',
        'executive_bullets',
        'suggested_tags',
        'suggested_category_id',
        'suggested_department_id',
        'entities',
        'confidence',
    ];

    protected $casts = [
        'executive_bullets' => 'array',
        'suggested_tags' => 'array',
        'entities' => 'array',
        'confidence' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DocumentAiRun::class, 'document_ai_run_id');
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function suggestedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'suggested_department_id');
    }
}
