<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAiRun>
 */
class DocumentAiRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_id' => Document::factory(),
            'document_version_id' => DocumentVersion::factory(),
            'triggered_by' => User::factory(),
            'provider' => fake()->randomElement(['openai', 'gemini']),
            'model' => fake()->randomElement(['gpt-4.1-mini', 'gemini-2.0-flash']),
            'status' => fake()->randomElement(['queued', 'running', 'success', 'failed', 'skipped']),
            'task' => fake()->randomElement(['summarize', 'extract', 'classify', 'embed']),
            'input_hash' => fake()->sha256(),
            'prompt_version' => 'v1.0.0',
            'tokens_in' => fake()->numberBetween(100, 5000),
            'tokens_out' => fake()->numberBetween(50, 1500),
            'cost_cents' => fake()->numberBetween(1, 1000),
            'error_message' => null,
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Provider timeout',
        ]);
    }
}
