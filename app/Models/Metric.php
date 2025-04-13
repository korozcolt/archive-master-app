<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Metric extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'user_id',
        'category_id',
        'metric_type',
        'metric_date',
        'value',
        'metadata',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'value' => 'float',
        'metadata' => 'json',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['metric_type', 'metric_date', 'value'])
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
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

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Método estático para registrar una métrica
    public static function record(
        string $metricType,
        float $value,
        int $companyId,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?int $userId = null,
        ?int $categoryId = null,
        ?array $metadata = null
    ): self
    {
        return self::create([
            'metric_type' => $metricType,
            'metric_date' => now()->toDateString(),
            'value' => $value,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'metadata' => $metadata,
        ]);
    }
}
