<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'url' => fake()->url(),
            'events' => fake()->randomElements(
                ['document.created', 'document.updated', 'document.deleted', 'document.status_changed', 'user.created', 'user.updated', 'workflow.transition'],
                fake()->numberBetween(1, 3)
            ),
            'name' => fake()->words(3, true),
            'secret' => fake()->optional()->sha256(),
            'active' => fake()->boolean(80), // 80% active
            'retry_attempts' => fake()->numberBetween(1, 5),
            'timeout' => fake()->randomElement([15, 30, 60, 120]),
            'last_triggered_at' => fake()->optional()->dateTimeBetween('-30 days'),
            'failed_attempts' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the webhook is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the webhook is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the webhook has never been triggered.
     */
    public function neverTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => null,
            'failed_attempts' => 0,
        ]);
    }

    /**
     * Indicate that the webhook has failures.
     */
    public function withFailures(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_attempts' => $count,
        ]);
    }
}
