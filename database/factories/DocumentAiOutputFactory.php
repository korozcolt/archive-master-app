<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Department;
use App\Models\DocumentAiRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAiOutput>
 */
class DocumentAiOutputFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_ai_run_id' => DocumentAiRun::factory(),
            'summary_md' => fake()->paragraph(3),
            'executive_bullets' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'suggested_tags' => ['contrato', 'urgente'],
            'suggested_category_id' => Category::factory(),
            'suggested_department_id' => Department::factory(),
            'entities' => [
                'people' => [fake()->name()],
                'dates' => [now()->toDateString()],
            ],
            'confidence' => [
                'summary' => 0.92,
                'classification' => 0.78,
            ],
        ];
    }
}
