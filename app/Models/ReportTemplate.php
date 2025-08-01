<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'report_type',
        'configuration',
        'is_public',
        'is_favorite',
        'user_id',
        'company_id',
        'usage_count',
        'last_used_at'
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_public' => 'boolean',
        'is_favorite' => 'boolean',
        'last_used_at' => 'datetime'
    ];

    /**
     * Get the user that owns the template
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the template
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Increment usage count and update last used timestamp
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope for public templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for favorite templates
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope for user's templates
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get popular templates based on usage
     */
    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')
                    ->orderBy('last_used_at', 'desc')
                    ->limit($limit);
    }
}