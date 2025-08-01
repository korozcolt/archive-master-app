<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'report_config',
        'schedule_frequency',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'email_recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'report_config' => 'json',
        'email_recipients' => 'json',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Get the user that owns the scheduled report
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active scheduled reports
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for reports that are due to run
     */
    public function scopeDueToRun($query)
    {
        return $query->where('is_active', true)
                    ->where('next_run_at', '<=', now());
    }

    /**
     * Calculate the next run time based on frequency
     */
    public function calculateNextRun(): void
    {
        $now = now();
        
        switch ($this->schedule_frequency) {
            case 'daily':
                $this->next_run_at = $now->copy()->addDay()->setTimeFromTimeString($this->schedule_time);
                break;
                
            case 'weekly':
                $this->next_run_at = $now->copy()->next($this->schedule_day_of_week)
                                         ->setTimeFromTimeString($this->schedule_time);
                break;
                
            case 'monthly':
                $this->next_run_at = $now->copy()->addMonth()
                                         ->day($this->schedule_day_of_month)
                                         ->setTimeFromTimeString($this->schedule_time);
                break;
                
            case 'quarterly':
                $this->next_run_at = $now->copy()->addQuarter()
                                         ->day($this->schedule_day_of_month ?? 1)
                                         ->setTimeFromTimeString($this->schedule_time);
                break;
        }
        
        $this->save();
    }

    /**
     * Mark the report as run
     */
    public function markAsRun(): void
    {
        $this->last_run_at = now();
        $this->calculateNextRun();
    }
}