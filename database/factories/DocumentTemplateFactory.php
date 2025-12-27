<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentTemplate::class;

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
            'icon' => $this->faker->randomElement([
                'heroicon-o-document-text',
                'heroicon-o-document-duplicate',
                'heroicon-o-clipboard-document',
                'heroicon-o-newspaper',
                'heroicon-o-document-chart-bar',
            ]),
            'color' => $this->faker->randomElement(['gray', 'blue', 'green', 'yellow', 'red', 'purple', 'pink']),
            'default_category_id' => null,
            'default_status_id' => null,
            'default_workflow_id' => null,
            'default_priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'default_is_confidential' => $this->faker->boolean(30),
            'default_tracking_enabled' => $this->faker->boolean(50),
            'custom_fields' => null,
            'required_fields' => ['title', 'description'],
            'allowed_file_types' => ['pdf', 'docx', 'xlsx'],
            'max_file_size_mb' => 10,
            'default_tags' => null,
            'suggested_tags' => null,
            'default_physical_location_id' => null,
            'document_number_prefix' => strtoupper($this->faker->lexify('???-')),
            'instructions' => $this->faker->paragraph(),
            'help_text' => $this->faker->sentence(),
            'is_active' => true,
            'usage_count' => $this->faker->numberBetween(0, 100),
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-6 months'),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template has custom fields.
     */
    public function withCustomFields(): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_fields' => [
                [
                    'name' => 'numero_contrato',
                    'label' => 'Número de Contrato',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'monto',
                    'label' => 'Monto',
                    'type' => 'number',
                    'required' => true,
                ],
                [
                    'name' => 'fecha_vencimiento',
                    'label' => 'Fecha de Vencimiento',
                    'type' => 'date',
                    'required' => false,
                ],
            ],
        ]);
    }

    /**
     * Template for contracts.
     */
    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Contrato de Servicio',
            'description' => 'Plantilla para contratos de prestación de servicios',
            'icon' => 'heroicon-o-document-text',
            'color' => 'blue',
            'document_number_prefix' => 'CONT-',
            'default_priority' => 'high',
            'default_is_confidential' => true,
            'allowed_file_types' => ['pdf', 'docx'],
            'custom_fields' => [
                ['name' => 'numero_contrato', 'label' => 'Número de Contrato', 'type' => 'text', 'required' => true],
                ['name' => 'contratante', 'label' => 'Contratante', 'type' => 'text', 'required' => true],
                ['name' => 'monto', 'label' => 'Monto', 'type' => 'number', 'required' => true],
                ['name' => 'fecha_inicio', 'label' => 'Fecha de Inicio', 'type' => 'date', 'required' => true],
                ['name' => 'fecha_fin', 'label' => 'Fecha de Fin', 'type' => 'date', 'required' => false],
            ],
            'required_fields' => ['title', 'description', 'file'],
        ]);
    }

    /**
     * Template for invoices.
     */
    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Factura Comercial',
            'description' => 'Plantilla para facturas y documentos de cobro',
            'icon' => 'heroicon-o-currency-dollar',
            'color' => 'green',
            'document_number_prefix' => 'FACT-',
            'default_priority' => 'medium',
            'default_is_confidential' => false,
            'default_tracking_enabled' => true,
            'allowed_file_types' => ['pdf', 'xml'],
            'custom_fields' => [
                ['name' => 'numero_factura', 'label' => 'Número de Factura', 'type' => 'text', 'required' => true],
                ['name' => 'rfc', 'label' => 'RFC', 'type' => 'text', 'required' => true],
                ['name' => 'monto_total', 'label' => 'Monto Total', 'type' => 'number', 'required' => true],
                ['name' => 'fecha_emision', 'label' => 'Fecha de Emisión', 'type' => 'date', 'required' => true],
            ],
            'required_fields' => ['title', 'file'],
        ]);
    }

    /**
     * Template for reports.
     */
    public function report(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Reporte Mensual',
            'description' => 'Plantilla para reportes mensuales de gestión',
            'icon' => 'heroicon-o-document-chart-bar',
            'color' => 'purple',
            'document_number_prefix' => 'REP-',
            'default_priority' => 'low',
            'default_is_confidential' => false,
            'allowed_file_types' => ['pdf', 'docx', 'xlsx'],
            'custom_fields' => [
                ['name' => 'periodo', 'label' => 'Periodo', 'type' => 'text', 'required' => true],
                ['name' => 'departamento', 'label' => 'Departamento', 'type' => 'text', 'required' => true],
                ['name' => 'responsable', 'label' => 'Responsable', 'type' => 'text', 'required' => true],
            ],
            'required_fields' => ['title', 'description', 'file'],
        ]);
    }

    /**
     * Template for correspondence.
     */
    public function correspondence(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Correspondencia Oficial',
            'description' => 'Plantilla para oficios y correspondencia oficial',
            'icon' => 'heroicon-o-envelope',
            'color' => 'yellow',
            'document_number_prefix' => 'OFIC-',
            'default_priority' => 'medium',
            'default_is_confidential' => false,
            'default_tracking_enabled' => true,
            'allowed_file_types' => ['pdf', 'docx'],
            'custom_fields' => [
                ['name' => 'destinatario', 'label' => 'Destinatario', 'type' => 'text', 'required' => true],
                ['name' => 'remitente', 'label' => 'Remitente', 'type' => 'text', 'required' => true],
                ['name' => 'asunto', 'label' => 'Asunto', 'type' => 'text', 'required' => true],
            ],
            'required_fields' => ['title', 'file'],
        ]);
    }

    /**
     * Template with high usage count.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(100, 500),
            'last_used_at' => $this->faker->dateTimeBetween('-1 week'),
        ]);
    }

    /**
     * Template recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(10, 50),
            'last_used_at' => $this->faker->dateTimeBetween('-3 days'),
        ]);
    }
}
