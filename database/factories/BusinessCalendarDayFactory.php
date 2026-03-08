<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendarDay>
 */
class BusinessCalendarDayFactory extends Factory
{
    protected $model = BusinessCalendarDay::class;

    public function definition(): array
    {
        return [
            'business_calendar_id' => BusinessCalendar::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'is_business_day' => false,
            'note' => fake()->sentence(),
        ];
    }
}
