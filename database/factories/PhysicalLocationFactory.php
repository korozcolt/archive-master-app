<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhysicalLocation>
 */
class PhysicalLocationFactory extends Factory
{
    protected $model = PhysicalLocation::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        $template = PhysicalLocationTemplate::create([
            'company_id' => $company->id,
            'name' => 'Estructura Estándar',
            'is_active' => true,
            'levels' => [
                ['order' => 1, 'name' => 'Edificio', 'code' => 'ED', 'required' => true],
                ['order' => 2, 'name' => 'Piso', 'code' => 'P', 'required' => true],
                ['order' => 3, 'name' => 'Sala', 'code' => 'SALA', 'required' => true],
            ],
        ]);

        return [
            'company_id' => $company->id,
            'template_id' => $template->id,
            'structured_data' => [
                'edificio' => fake()->randomElement(['A', 'B', 'C']),
                'piso' => fake()->numberBetween(1, 5),
                'sala' => fake()->randomElement(['Archivo', 'Almacén', 'Bodega']),
            ],
            'capacity_total' => fake()->numberBetween(50, 200),
            'capacity_used' => 0,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Ubicación con capacidad limitada
     */
    public function withLimitedCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity_total' => 100,
            'capacity_used' => 75,
        ]);
    }

    /**
     * Ubicación llena (sin capacidad)
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity_total' => 100,
            'capacity_used' => 100,
        ]);
    }

    /**
     * Ubicación inactiva
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Ubicación sin límite de capacidad
     */
    public function unlimitedCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity_total' => null,
            'capacity_used' => 0,
        ]);
    }
}
