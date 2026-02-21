<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'created_by' => User::factory(),
            'version_number' => 2,
            'content' => fake()->paragraph(6),
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'file_type' => null,
            'is_current' => false,
            'change_summary' => 'Versión 2',
            'metadata' => [],
        ];
    }
}
