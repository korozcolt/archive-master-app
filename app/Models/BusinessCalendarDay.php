<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCalendarDay extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'business_calendar_id',
        'date',
        'is_business_day',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_business_day' => 'boolean',
        ];
    }

    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }
}
