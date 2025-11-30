<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = \App\Models\Branch::factory()->create();

        return [
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'name' => fake()->randomElement(['Administración', 'Ventas', 'Recursos Humanos', 'Contabilidad', 'IT', 'Marketing']),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }
}
