<?php

namespace Database\Factories;

use App\Enums\DocumentAccessLevel;
use App\Models\Company;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentaryType>
 */
class DocumentaryTypeFactory extends Factory
{
    protected $model = DocumentaryType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'documentary_subseries_id' => DocumentarySubseries::factory(),
            'code' => strtoupper(fake()->bothify('TIP-##')),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'access_level_default' => DocumentAccessLevel::Interno,
            'is_active' => true,
        ];
    }
}
