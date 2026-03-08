<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentarySeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentarySeries>
 */
class DocumentarySeriesFactory extends Factory
{
    protected $model = DocumentarySeries::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->bothify('SER-##')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
