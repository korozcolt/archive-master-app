<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Status>
 */
class StatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Status::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ['es' => $this->faker->randomElement(['Borrador', 'En Revisión', 'Aprobado', 'Archivado', 'Rechazado'])],
            'slug' => $this->faker->slug(),
            'description' => ['es' => $this->faker->sentence()],
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['clock', 'check', 'x', 'archive', 'eye']),
            'order' => $this->faker->numberBetween(1, 100),
            'is_initial' => false,
            'is_final' => $this->faker->boolean(20), // 20% chance of being final
            'active' => true,
            'settings' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the status is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the status is final.
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
        ]);
    }

    /**
     * Indicate that the status is initial.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_initial' => true,
        ]);
    }
}