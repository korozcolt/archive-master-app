<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'branch_id' => \App\Models\Branch::factory(),
            'department_id' => \App\Models\Department::factory(),
            'category_id' => \App\Models\Category::factory(),
            'status_id' => \App\Models\Status::factory(),
            'created_by' => \App\Models\User::factory(),
            'assigned_to' => \App\Models\User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'document_number' => 'DOC-' . fake()->unique()->numberBetween(1000, 9999),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'is_confidential' => fake()->boolean(20), // 20% chance de ser confidencial
        ];
    }
}
