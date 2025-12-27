<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener empresas existentes (creadas por UserSeeder)
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->info('No hay empresas disponibles. Ejecute UserSeeder primero.');
            return;
        }

        // Definir plantillas base que se crearán para cada empresa
        $baseTemplates = [
            [
                'name' => 'Contrato de Servicio',
                'description' => 'Plantilla para contratos de prestación de servicios con clientes',
                'icon' => 'heroicon-o-document-text',
                'color' => 'blue',
                'category_slug' => 'contratos-comerciales',
                'document_number_prefix' => 'CONT-',
                'default_priority' => 'high',
                'default_is_confidential' => true,
                'default_tracking_enabled' => false,
                'allowed_file_types' => ['pdf', 'docx'],
                'max_file_size_mb' => 20,
                'custom_fields' => [
                    ['name' => 'numero_contrato', 'label' => 'Número de Contrato', 'type' => 'text', 'required' => true],
                    ['name' => 'contratante', 'label' => 'Contratante', 'type' => 'text', 'required' => true],
                    ['name' => 'monto', 'label' => 'Monto', 'type' => 'number', 'required' => true],
                    ['name' => 'fecha_inicio', 'label' => 'Fecha de Inicio', 'type' => 'date', 'required' => true],
                    ['name' => 'fecha_fin', 'label' => 'Fecha de Fin', 'type' => 'date', 'required' => false],
                    ['name' => 'vigencia', 'label' => 'Vigencia (meses)', 'type' => 'number', 'required' => false],
                ],
                'required_fields' => ['title', 'description', 'file', 'category_id'],
                'instructions' => '<p>Complete todos los campos requeridos del contrato. Asegúrese de adjuntar el documento firmado en formato PDF.</p>',
                'help_text' => 'Los contratos comerciales deben incluir el número de contrato, monto y fechas de vigencia.',
            ],
            [
                'name' => 'Factura Comercial',
                'description' => 'Plantilla para facturas de venta y documentos fiscales',
                'icon' => 'heroicon-o-currency-dollar',
                'color' => 'green',
                'category_slug' => 'facturas-de-venta',
                'document_number_prefix' => 'FACT-',
                'default_priority' => 'medium',
                'default_is_confidential' => false,
                'default_tracking_enabled' => true,
                'allowed_file_types' => ['pdf', 'xml'],
                'max_file_size_mb' => 5,
                'custom_fields' => [
                    ['name' => 'numero_factura', 'label' => 'Número de Factura', 'type' => 'text', 'required' => true],
                    ['name' => 'rfc_cliente', 'label' => 'RFC del Cliente', 'type' => 'text', 'required' => true],
                    ['name' => 'monto_subtotal', 'label' => 'Subtotal', 'type' => 'number', 'required' => true],
                    ['name' => 'monto_iva', 'label' => 'IVA', 'type' => 'number', 'required' => true],
                    ['name' => 'monto_total', 'label' => 'Monto Total', 'type' => 'number', 'required' => true],
                    ['name' => 'fecha_emision', 'label' => 'Fecha de Emisión', 'type' => 'date', 'required' => true],
                    ['name' => 'uuid', 'label' => 'UUID Fiscal', 'type' => 'text', 'required' => false],
                ],
                'required_fields' => ['title', 'file'],
                'instructions' => '<p>Adjunte la factura en formato PDF y XML. Complete todos los campos fiscales requeridos.</p>',
                'help_text' => 'Las facturas deben incluir el UUID fiscal y estar timbradas correctamente.',
            ],
            [
                'name' => 'Reporte Mensual',
                'description' => 'Plantilla para reportes mensuales de gestión y análisis',
                'icon' => 'heroicon-o-document-chart-bar',
                'color' => 'purple',
                'category_slug' => 'documentos-internos',
                'document_number_prefix' => 'REP-',
                'default_priority' => 'low',
                'default_is_confidential' => false,
                'default_tracking_enabled' => false,
                'allowed_file_types' => ['pdf', 'docx', 'xlsx', 'pptx'],
                'max_file_size_mb' => 15,
                'custom_fields' => [
                    ['name' => 'periodo', 'label' => 'Periodo (Mes/Año)', 'type' => 'text', 'required' => true],
                    ['name' => 'departamento', 'label' => 'Departamento', 'type' => 'text', 'required' => true],
                    ['name' => 'responsable', 'label' => 'Responsable', 'type' => 'text', 'required' => true],
                    ['name' => 'tipo_reporte', 'label' => 'Tipo de Reporte', 'type' => 'text', 'required' => false],
                ],
                'required_fields' => ['title', 'description', 'file'],
                'instructions' => '<p>Complete el reporte mensual con las métricas y análisis correspondientes. Incluya gráficas y tablas si es necesario.</p>',
                'help_text' => 'Los reportes mensuales deben incluir el periodo y el departamento responsable.',
            ],
            [
                'name' => 'Correspondencia Oficial',
                'description' => 'Plantilla para oficios y comunicaciones oficiales',
                'icon' => 'heroicon-o-envelope',
                'color' => 'yellow',
                'category_slug' => 'correspondencia-saliente',
                'document_number_prefix' => 'OFIC-',
                'default_priority' => 'medium',
                'default_is_confidential' => false,
                'default_tracking_enabled' => true,
                'allowed_file_types' => ['pdf', 'docx'],
                'max_file_size_mb' => 10,
                'custom_fields' => [
                    ['name' => 'destinatario', 'label' => 'Destinatario', 'type' => 'text', 'required' => true],
                    ['name' => 'cargo_destinatario', 'label' => 'Cargo del Destinatario', 'type' => 'text', 'required' => false],
                    ['name' => 'remitente', 'label' => 'Remitente', 'type' => 'text', 'required' => true],
                    ['name' => 'cargo_remitente', 'label' => 'Cargo del Remitente', 'type' => 'text', 'required' => false],
                    ['name' => 'asunto', 'label' => 'Asunto', 'type' => 'text', 'required' => true],
                    ['name' => 'fecha_oficio', 'label' => 'Fecha del Oficio', 'type' => 'date', 'required' => true],
                ],
                'required_fields' => ['title', 'file'],
                'instructions' => '<p>Complete los campos de destinatario, remitente y asunto. El oficio debe estar firmado y con membrete oficial.</p>',
                'help_text' => 'La correspondencia oficial debe incluir fecha, destinatario y asunto claramente identificados.',
            ],
            [
                'name' => 'Solicitud Interna',
                'description' => 'Plantilla para solicitudes de recursos, permisos o autorizaciones internas',
                'icon' => 'heroicon-o-document-plus',
                'color' => 'indigo',
                'category_slug' => 'memorandos',
                'document_number_prefix' => 'SOL-',
                'default_priority' => 'medium',
                'default_is_confidential' => false,
                'default_tracking_enabled' => true,
                'allowed_file_types' => ['pdf', 'docx'],
                'max_file_size_mb' => 5,
                'custom_fields' => [
                    ['name' => 'tipo_solicitud', 'label' => 'Tipo de Solicitud', 'type' => 'text', 'required' => true],
                    ['name' => 'solicitante', 'label' => 'Solicitante', 'type' => 'text', 'required' => true],
                    ['name' => 'departamento_solicitante', 'label' => 'Departamento', 'type' => 'text', 'required' => true],
                    ['name' => 'justificacion', 'label' => 'Justificación', 'type' => 'text', 'required' => true],
                    ['name' => 'fecha_requerida', 'label' => 'Fecha Requerida', 'type' => 'date', 'required' => false],
                ],
                'required_fields' => ['title', 'description'],
                'instructions' => '<p>Complete todos los campos de la solicitud y proporcione una justificación clara. Adjunte documentación de soporte si es necesario.</p>',
                'help_text' => 'Las solicitudes deben incluir tipo, solicitante, departamento y justificación.',
            ],
            [
                'name' => 'Acta de Reunión',
                'description' => 'Plantilla para actas de reuniones y juntas',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => 'pink',
                'category_slug' => 'actas-de-reunion',
                'document_number_prefix' => 'ACTA-',
                'default_priority' => 'low',
                'default_is_confidential' => false,
                'default_tracking_enabled' => false,
                'allowed_file_types' => ['pdf', 'docx'],
                'max_file_size_mb' => 10,
                'custom_fields' => [
                    ['name' => 'fecha_reunion', 'label' => 'Fecha de Reunión', 'type' => 'date', 'required' => true],
                    ['name' => 'hora_inicio', 'label' => 'Hora de Inicio', 'type' => 'text', 'required' => true],
                    ['name' => 'hora_fin', 'label' => 'Hora de Fin', 'type' => 'text', 'required' => true],
                    ['name' => 'lugar', 'label' => 'Lugar', 'type' => 'text', 'required' => false],
                    ['name' => 'participantes', 'label' => 'Participantes', 'type' => 'text', 'required' => true],
                    ['name' => 'secretario', 'label' => 'Secretario', 'type' => 'text', 'required' => false],
                ],
                'required_fields' => ['title', 'description', 'file'],
                'instructions' => '<p>Registre todos los acuerdos y compromisos de la reunión. Incluya la lista de participantes y el orden del día.</p>',
                'help_text' => 'Las actas deben incluir fecha, hora, participantes y los acuerdos tomados.',
            ],
        ];

        // Crear plantillas para cada empresa
        foreach ($companies as $company) {
            $this->command->info("Creando plantillas de documentos para la empresa: {$company->name}");

            // Obtener el primer usuario admin de la empresa para asignar created_by
            $adminUser = User::where('company_id', $company->id)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })
                ->first();

            if (!$adminUser) {
                $adminUser = User::where('company_id', $company->id)->first();
            }

            // Obtener el estado inicial de la empresa
            $initialStatus = Status::where('company_id', $company->id)
                ->where('is_initial', true)
                ->first();

            // Crear cada plantilla
            foreach ($baseTemplates as $templateData) {
                // Buscar la categoría por slug
                $category = Category::where('company_id', $company->id)
                    ->where('slug', $templateData['category_slug'])
                    ->first();

                // Si no encuentra la categoría específica, buscar la categoría padre
                if (!$category && isset($templateData['category_slug'])) {
                    $slugParts = explode('-', $templateData['category_slug']);
                    $parentSlug = $slugParts[0];
                    $category = Category::where('company_id', $company->id)
                        ->where('slug', 'like', $parentSlug . '%')
                        ->first();
                }

                DocumentTemplate::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $templateData['name'],
                    ],
                    [
                        'description' => $templateData['description'],
                        'icon' => $templateData['icon'],
                        'color' => $templateData['color'],
                        'default_category_id' => $category?->id,
                        'default_status_id' => $initialStatus?->id,
                        'default_workflow_id' => null,
                        'default_priority' => $templateData['default_priority'],
                        'default_is_confidential' => $templateData['default_is_confidential'],
                        'default_tracking_enabled' => $templateData['default_tracking_enabled'],
                        'custom_fields' => $templateData['custom_fields'],
                        'required_fields' => $templateData['required_fields'],
                        'allowed_file_types' => $templateData['allowed_file_types'],
                        'max_file_size_mb' => $templateData['max_file_size_mb'],
                        'default_tags' => null,
                        'suggested_tags' => null,
                        'default_physical_location_id' => null,
                        'document_number_prefix' => $templateData['document_number_prefix'],
                        'instructions' => $templateData['instructions'],
                        'help_text' => $templateData['help_text'],
                        'is_active' => true,
                        'usage_count' => rand(5, 50),
                        'last_used_at' => now()->subDays(rand(1, 30)),
                        'created_by' => $adminUser?->id,
                        'updated_by' => null,
                    ]
                );
            }

            // Crear una plantilla inactiva para pruebas
            DocumentTemplate::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Plantilla Inactiva de Prueba',
                ],
                [
                    'description' => 'Esta es una plantilla de prueba desactivada',
                    'icon' => 'heroicon-o-archive-box-x-mark',
                    'color' => 'gray',
                    'default_category_id' => null,
                    'default_status_id' => $initialStatus?->id,
                    'default_priority' => 'low',
                    'default_is_confidential' => false,
                    'default_tracking_enabled' => false,
                    'custom_fields' => null,
                    'required_fields' => ['title'],
                    'allowed_file_types' => ['pdf'],
                    'max_file_size_mb' => 5,
                    'document_number_prefix' => 'TEST-',
                    'instructions' => 'Plantilla de prueba',
                    'help_text' => 'No usar en producción',
                    'is_active' => false,
                    'usage_count' => 0,
                    'last_used_at' => null,
                    'created_by' => $adminUser?->id,
                    'updated_by' => null,
                ]
            );

            $this->command->info("  ✓ Se crearon " . (count($baseTemplates) + 1) . " plantillas para {$company->name}");
        }

        $this->command->info('✓ Plantillas de documentos creadas exitosamente');
    }
}
