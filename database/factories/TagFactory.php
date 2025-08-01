<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ['es' => $this->faker->word()],
            'slug' => $this->faker->slug(),
            'description' => ['es' => $this->faker->sentence()],
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['tag', 'bookmark', 'star', 'flag', 'label']),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the tag is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}