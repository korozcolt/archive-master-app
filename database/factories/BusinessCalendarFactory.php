<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendar>
 */
class BusinessCalendarFactory extends Factory
{
    protected $model = BusinessCalendar::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Calendario hábil',
            'country_code' => 'CO',
            'timezone' => 'America/Bogota',
            'weekend_days' => [0, 6],
            'is_default' => true,
            'metadata' => [],
        ];
    }
}
