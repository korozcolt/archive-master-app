<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AguasDeSucreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        foreach ($this->definitions() as $index => $definition) {
            $parent = Category::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => Str::slug($definition['name']),
                ],
                [
                    'parent_id' => null,
                    'name' => ['es' => $definition['name']],
                    'description' => ['es' => $definition['description']],
                    'color' => $definition['color'],
                    'icon' => $definition['icon'],
                    'order' => $index + 1,
                    'active' => true,
                    'settings' => null,
                ],
            );

            foreach ($definition['children'] as $childIndex => $childDefinition) {
                Category::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($childDefinition['name']),
                    ],
                    [
                        'parent_id' => $parent->id,
                        'name' => ['es' => $childDefinition['name']],
                        'description' => ['es' => $childDefinition['description']],
                        'color' => $childDefinition['color'] ?? $definition['color'],
                        'icon' => $childDefinition['icon'] ?? $definition['icon'],
                        'order' => $childIndex + 1,
                        'active' => true,
                        'settings' => null,
                    ],
                );
            }
        }

        $this->command?->info('Categorías operativas creadas para AGUAS DE SUCRE.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Correspondencia',
                'description' => 'Documentos de entrada y salida de la entidad.',
                'color' => '#2563eb',
                'icon' => 'heroicon-o-envelope',
                'children' => [
                    $this->child('Correspondencia Recibida', 'Documentos recibidos desde ciudadanos, entidades y contratistas.', 'heroicon-o-inbox'),
                    $this->child('Correspondencia Enviada', 'Comunicaciones oficiales emitidas por la entidad.', 'heroicon-o-paper-airplane'),
                    $this->child('Comunicaciones Internas', 'Memorandos, circulares y comunicaciones de uso interno.', 'heroicon-o-chat-bubble-left-right'),
                ],
            ],
            [
                'name' => 'Atención al Ciudadano',
                'description' => 'Documentos relacionados con PQRS y peticiones.',
                'color' => '#0f766e',
                'icon' => 'heroicon-o-user-circle',
                'children' => [
                    $this->child('PQRS', 'Peticiones, quejas, reclamos y sugerencias.', 'heroicon-o-chat-bubble-left-ellipsis'),
                    $this->child('Derechos de Petición', 'Solicitudes formales elevadas por ciudadanos o entidades.', 'heroicon-o-document-text'),
                    $this->child('Tutelas y Acciones Constitucionales', 'Tutelas, acciones de grupo y demás acciones constitucionales.', 'heroicon-o-scale'),
                ],
            ],
            [
                'name' => 'Contratación',
                'description' => 'Documentos contractuales y de supervisión.',
                'color' => '#7c3aed',
                'icon' => 'heroicon-o-briefcase',
                'children' => [
                    $this->child('Contratos', 'Contratos de obra, suministro, servicios, consultoría e interventoría.', 'heroicon-o-document-duplicate'),
                    $this->child('Convenios', 'Convenios interinstitucionales y documentos asociados.', 'heroicon-o-link'),
                    $this->child('Supervisión Contractual', 'Informes, certificaciones y soportes de supervisión.', 'heroicon-o-clipboard-document-check'),
                ],
            ],
            [
                'name' => 'Financiera y Contable',
                'description' => 'Documentos presupuestales, contables y tributarios.',
                'color' => '#15803d',
                'icon' => 'heroicon-o-banknotes',
                'children' => [
                    $this->child('Facturación y Cobro', 'Facturas, cuentas de cobro y soportes de recaudo.', 'heroicon-o-receipt-percent'),
                    $this->child('Presupuesto y Tesorería', 'CDP, RP, movimientos presupuestales y tesorería.', 'heroicon-o-calculator'),
                    $this->child('Contabilidad y Estados Financieros', 'Comprobantes, libros y estados financieros.', 'heroicon-o-presentation-chart-line'),
                    $this->child('Tributaria', 'Declaraciones y obligaciones tributarias.', 'heroicon-o-building-library'),
                ],
            ],
            [
                'name' => 'Talento Humano',
                'description' => 'Documentos de personal y gestión laboral.',
                'color' => '#ea580c',
                'icon' => 'heroicon-o-users',
                'children' => [
                    $this->child('Historias Laborales', 'Expedientes laborales de funcionarios y contratistas.', 'heroicon-o-folder'),
                    $this->child('Nómina', 'Soportes de nómina y prestaciones.', 'heroicon-o-currency-dollar'),
                    $this->child('Seguridad y Salud en el Trabajo', 'Copasst, SGSST, bienestar y seguridad laboral.', 'heroicon-o-shield-check'),
                ],
            ],
            [
                'name' => 'Jurídica y Control',
                'description' => 'Procesos jurídicos, control interno y auditoría.',
                'color' => '#b91c1c',
                'icon' => 'heroicon-o-scale',
                'children' => [
                    $this->child('Procesos Jurídicos', 'Procesos disciplinarios, contenciosos y actuaciones jurídicas.', 'heroicon-o-scale'),
                    $this->child('Control Interno y Auditoría', 'Auditorías, seguimientos e informes de control interno.', 'heroicon-o-clipboard-document-list'),
                    $this->child('Actos Administrativos', 'Resoluciones, certificaciones y actuaciones administrativas.', 'heroicon-o-document-check'),
                ],
            ],
            [
                'name' => 'Planeación y Gestión',
                'description' => 'Documentos de planeación, seguimiento y mejora.',
                'color' => '#ca8a04',
                'icon' => 'heroicon-o-chart-bar-square',
                'children' => [
                    $this->child('Planes y Programas', 'Planes estratégicos, planes anuales y programas institucionales.', 'heroicon-o-map'),
                    $this->child('Informes', 'Informes de gestión, rendición de cuentas y entes de control.', 'heroicon-o-document-chart-bar'),
                    $this->child('Actas', 'Actas de comités, juntas y reuniones.', 'heroicon-o-clipboard-document-list'),
                ],
            ],
            [
                'name' => 'Archivo y Gestión Documental',
                'description' => 'Documentos archivísticos e instrumentos de gestión documental.',
                'color' => '#475569',
                'icon' => 'heroicon-o-archive-box',
                'children' => [
                    $this->child('Instrumentos Archivísticos', 'TRD, CCD, PGD, PINAR y demás instrumentos archivísticos.', 'heroicon-o-archive-box'),
                    $this->child('Inventarios y Registros', 'Inventarios, registros y controles documentales.', 'heroicon-o-table-cells'),
                    $this->child('Manuales y Procedimientos', 'Manuales, guías y procedimientos institucionales.', 'heroicon-o-book-open'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function child(string $name, string $description, string $icon): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
        ];
    }
}
