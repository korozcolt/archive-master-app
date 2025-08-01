<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => ['es' => $this->faker->words(2, true)],
            'slug' => $this->faker->slug(),
            'description' => ['es' => $this->faker->sentence()],
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['folder', 'file', 'archive', 'document', 'image']),
            'order' => $this->faker->numberBetween(1, 100),
            'active' => true,
            'settings' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the category has a parent.
     */
    public function withParent(Category $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent ? $parent->id : Category::factory(),
            'company_id' => $parent ? $parent->company_id : $attributes['company_id'],
        ]);
    }
}