<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentarySubseries>
 */
class DocumentarySubseriesFactory extends Factory
{
    protected $model = DocumentarySubseries::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'documentary_series_id' => DocumentarySeries::factory(),
            'code' => strtoupper(fake()->bothify('SUB-##')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
