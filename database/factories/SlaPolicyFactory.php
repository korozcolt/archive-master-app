<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'business_calendar_id' => null,
            'code' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'legal_basis' => 'Ley 1755 de 2015',
            'response_term_days' => 15,
            'warning_days' => [3, 1],
            'escalation_days' => 1,
            'remission_deadline_days' => 5,
            'requires_subsanation' => true,
            'allows_extension' => true,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
