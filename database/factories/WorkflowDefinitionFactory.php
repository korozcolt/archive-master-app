<?php

namespace Database\Factories;

use App\Models\WorkflowDefinition;
use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowDefinition>
 */
class WorkflowDefinitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WorkflowDefinition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'from_status_id' => Status::factory(),
            'to_status_id' => Status::factory(),
            'requires_approval' => $this->faker->boolean(),
            'approval_config' => [
                'approvers' => [],
                'min_approvals' => 1,
            ],
            'active' => true,
        ];
    }

    /**
     * Indicate that this workflow requires approval.
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => true,
        ]);
    }

    /**
     * Indicate that this workflow does not require approval.
     */
    public function noApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => false,
        ]);
    }
}
