<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyAiSetting>
 */
class CompanyAiSettingFactory extends Factory
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
            'provider' => fake()->randomElement(['none', 'openai', 'gemini']),
            'api_key_encrypted' => null,
            'is_enabled' => false,
            'monthly_budget_cents' => 100000,
            'daily_doc_limit' => 200,
            'max_pages_per_doc' => 150,
            'store_outputs' => true,
            'redact_pii' => true,
        ];
    }

    public function enabledWithProvider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'is_enabled' => $provider !== 'none',
            'api_key_encrypted' => $provider === 'none' ? null : 'test-key-'.$provider,
        ]);
    }
}
