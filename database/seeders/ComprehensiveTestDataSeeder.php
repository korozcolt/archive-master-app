<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder comprehensivo para QA y regresión.
 *
 * Crea:
 *  - Permisos y roles de Spatie
 *  - 1 empresa principal con 3 sucursales y 6 departamentos
 *  - 1 usuario por cada rol (7 usuarios) + el super admin original
 *  - Categorías, estados y tags
 *  - Documentos reales (PDF generados) distribuidos por rol/departamento
 */
class ComprehensiveTestDataSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $users = [];

    /** @var array<int, Status> */
    private array $statuses = [];

    /** @var array<int, Category> */
    private array $categories = [];

    /** @var array<int, Tag> */
    private array $tags = [];

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissionsAndRoles();

        $company = $this->seedCompany();
        $branches = $this->seedBranches($company);
        $departments = $this->seedDepartments($company, $branches);

        $this->seedUsers($company, $branches, $departments);
        $this->statuses = $this->seedStatuses($company);
        $this->categories = $this->seedCategories($company);
        $this->tags = $this->seedTags($company);

        $this->seedDocumentsWithRealFiles($company, $branches, $departments);

        $this->command?->info('✅  ComprehensiveTestDataSeeder completado.');
        $this->command?->table(
            ['Rol', 'Email', 'Password'],
            collect($this->users)->map(fn (User $u) => [
                $u->roles->first()?->name ?? '—',
                $u->email,
                'TestPass123!',
            ])->toArray()
        );
    }

    // ── Permissions & Roles ─────────────────────────────────────────

    private function seedPermissionsAndRoles(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        foreach (Role::getAllPermissions() as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (Role::cases() as $role) {
            $spatieRole = SpatieRole::findByName($role->value, 'web');
            $permissions = $role->getPermissions();

            if (in_array('*', $permissions)) {
                $spatieRole->syncPermissions(Permission::all());
            } else {
                $spatieRole->syncPermissions($permissions);
            }
        }
    }

    // ── Company ─────────────────────────────────────────────────────

    private function seedCompany(): Company
    {
        return Company::firstOrCreate(
            ['name' => 'ArchiveMaster Corp'],
            [
                'legal_name' => 'ArchiveMaster Corporation S.A.S.',
                'tax_id' => '900123456-7',
                'address' => 'Calle Principal 123, Bogotá',
                'phone' => '+57 601 1234567',
                'email' => 'info@archivemaster.com',
                'website' => 'https://www.archivemaster.com',
                'primary_color' => '#41a6b3',
                'secondary_color' => '#f59e0b',
                'active' => true,
            ]
        );
    }

    /** @return Branch[] */
    private function seedBranches(Company $company): array
    {
        $data = [
            ['name' => 'Sede Principal', 'code' => 'HQ', 'city' => 'Bogotá'],
            ['name' => 'Sucursal Norte', 'code' => 'NORTH', 'city' => 'Medellín'],
            ['name' => 'Sucursal Sur', 'code' => 'SOUTH', 'city' => 'Cali'],
        ];

        return array_map(fn (array $b) => Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => $b['code']],
            ['name' => $b['name'], 'city' => $b['city'], 'country' => 'Colombia', 'active' => true]
        ), $data);
    }

    /** @return Department[] */
    private function seedDepartments(Company $company, array $branches): array
    {
        $data = [
            ['name' => 'Administración', 'code' => 'ADMIN', 'branch' => 0],
            ['name' => 'Recursos Humanos', 'code' => 'RRHH', 'branch' => 0],
            ['name' => 'Contabilidad', 'code' => 'CONT', 'branch' => 0],
            ['name' => 'Archivo Central', 'code' => 'ARCH', 'branch' => 0],
            ['name' => 'Ventas Norte', 'code' => 'SALES-N', 'branch' => 1],
            ['name' => 'Ventas Sur', 'code' => 'SALES-S', 'branch' => 2],
        ];

        return array_map(fn (array $d) => Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => $d['code']],
            ['name' => $d['name'], 'branch_id' => $branches[$d['branch']]->id, 'active' => true]
        ), $data);
    }

    // ── Users ───────────────────────────────────────────────────────

    private function seedUsers(Company $company, array $branches, array $departments): void
    {
        $password = Hash::make('TestPass123!');

        $specs = [
            ['Kristian Orozco', 'superadmin@archivemaster.test', Role::SuperAdmin, 'CEO / Super Admin', 0, 0],
            ['Carlos Admin', 'admin@archivemaster.test', Role::Admin, 'Administrador General', 0, 0],
            ['Diana Sucursal', 'branch@archivemaster.test', Role::BranchAdmin, 'Gerente Sucursal Norte', 1, 4],
            ['Elena Oficina', 'office@archivemaster.test', Role::OfficeManager, 'Jefa de RRHH', 0, 1],
            ['Fernando Archivo', 'archive@archivemaster.test', Role::ArchiveManager, 'Jefe de Archivo', 0, 3],
            ['Gloria Recepción', 'reception@archivemaster.test', Role::Receptionist, 'Recepcionista Principal', 0, 0],
            ['Hugo Usuario', 'user@archivemaster.test', Role::RegularUser, 'Contador Junior', 0, 2],
        ];

        foreach ($specs as [$name, $email, $role, $position, $branchIdx, $deptIdx]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'company_id' => $company->id,
                    'branch_id' => $branches[$branchIdx]->id,
                    'department_id' => $departments[$deptIdx]->id,
                    'position' => $position,
                    'phone' => '+57 300 '.fake()->numerify('#######'),
                    'language' => 'es',
                    'timezone' => 'America/Bogota',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$role->value]);
            $this->users[$role->value] = $user;
        }
    }

    // ── Statuses ────────────────────────────────────────────────────

    /** @return Status[] */
    private function seedStatuses(Company $company): array
    {
        $data = [
            ['Borrador', 'borrador', '#6b7280', 'pencil', true, false],
            ['Recibido', 'recibido', '#3b82f6', 'inbox', false, false],
            ['En Proceso', 'en-proceso', '#f59e0b', 'clock', false, false],
            ['En Revisión', 'en-revision', '#8b5cf6', 'eye', false, false],
            ['Aprobado', 'aprobado', '#10b981', 'check-circle', false, true],
            ['Rechazado', 'rechazado', '#ef4444', 'x-circle', false, true],
            ['Archivado', 'archivado', '#6366f1', 'archive', false, true],
        ];

        return array_map(fn (array $s) => Status::firstOrCreate(
            ['company_id' => $company->id, 'slug' => $s[1]],
            [
                'name' => ['es' => $s[0], 'en' => $s[0]],
                'color' => $s[2],
                'icon' => $s[3],
                'is_initial' => $s[4],
                'is_final' => $s[5],
                'active' => true,
                'order' => array_search($s, $data) + 1,
            ]
        ), $data);
    }

    // ── Categories ──────────────────────────────────────────────────

    /** @return Category[] */
    private function seedCategories(Company $company): array
    {
        $names = [
            'Correspondencia',
            'Facturas',
            'Contratos',
            'Documentos Internos',
            'Documentos Legales',
            'Informes',
            'Memorandos',
        ];

        return array_map(fn (string $name) => Category::firstOrCreate(
            ['company_id' => $company->id, 'slug' => \Str::slug($name)],
            [
                'name' => ['es' => $name, 'en' => $name],
                'description' => ['es' => "Categoría para {$name}"],
                'color' => fake()->hexColor(),
                'icon' => 'folder',
                'active' => true,
            ]
        ), $names);
    }

    // ── Tags ────────────────────────────────────────────────────────

    /** @return Tag[] */
    private function seedTags(Company $company): array
    {
        $tags = [
            ['Urgente', '#ef4444'],
            ['Confidencial', '#7c3aed'],
            ['Original', '#059669'],
            ['Copia', '#6b7280'],
            ['Pendiente Firma', '#f59e0b'],
            ['Digitalizado', '#3b82f6'],
            ['Vencido', '#dc2626'],
            ['Revisado', '#10b981'],
        ];

        return array_map(fn (array $t) => Tag::firstOrCreate(
            ['company_id' => $company->id, 'slug' => \Str::slug($t[0])],
            [
                'name' => ['es' => $t[0], 'en' => $t[0]],
                'color' => $t[1],
                'icon' => 'tag',
                'active' => true,
            ]
        ), $tags);
    }

    // ── Documents with real PDF files ───────────────────────────────

    private function seedDocumentsWithRealFiles(Company $company, array $branches, array $departments): void
    {
        Storage::disk('public')->makeDirectory('documents/seed');

        $documentSpecs = $this->getDocumentSpecs();

        foreach ($documentSpecs as $spec) {
            $creator = $this->users[$spec['creator_role']];
            $assignee = isset($spec['assignee_role']) ? $this->users[$spec['assignee_role']] : $creator;

            $existingDocument = Document::query()
                ->where('company_id', $company->id)
                ->where('created_by', $creator->id)
                ->where('title', $spec['title'])
                ->first();

            if ($existingDocument) {
                $this->command?->info("  ↷ [{$spec['creator_role']}] {$spec['title']} (ya existe)");

                continue;
            }

            // Generate a real PDF file
            $pdfPath = $this->generatePdf($spec['title'], $spec['content'], $creator->name);

            $document = Document::create([
                'company_id' => $company->id,
                'branch_id' => $creator->branch_id,
                'department_id' => $creator->department_id,
                'category_id' => $this->categories[array_rand($this->categories)]->id,
                'status_id' => $this->statuses[$spec['status_index']]->id,
                'created_by' => $creator->id,
                'assigned_to' => $assignee->id,
                'title' => $spec['title'],
                'description' => $spec['description'],
                'content' => $spec['content'],
                'file_path' => $pdfPath,
                'priority' => $spec['priority'],
                'is_confidential' => $spec['confidential'] ?? false,
                'metadata' => [
                    'file_name' => basename($pdfPath),
                    'file_size' => Storage::disk('public')->size($pdfPath),
                    'mime_type' => 'application/pdf',
                    'seeder' => 'ComprehensiveTestDataSeeder',
                ],
            ]);

            // Attach random tags (1-3)
            $tagIds = collect($this->tags)->random(rand(1, 3))->pluck('id')->toArray();
            $document->tags()->attach($tagIds);

            $this->command?->info("  📄 [{$spec['creator_role']}] {$spec['title']}");
        }

        // Generate a plain text file for variety
        $this->generateTextDocument($company);

        // Generate an HTML-based document for variety
        $this->generateHtmlDocument($company);
    }

    private function generatePdf(string $title, string $content, string $author): string
    {
        $html = view('pdf.seed-document', [
            'title' => $title,
            'content' => $content,
            'author' => $author,
            'date' => now()->format('d/m/Y'),
            'company' => 'ArchiveMaster Corp',
        ])->render();

        $filename = 'documents/seed/'.\Str::slug($title).'-'.uniqid().'.pdf';

        $pdf = Pdf::loadHTML($html);
        Storage::disk('public')->put($filename, $pdf->output());
        unset($pdf);
        gc_collect_cycles();

        return $filename;
    }

    private function generateTextDocument(Company $company): void
    {
        $creator = $this->users[Role::Receptionist->value];
        $content = "ACTA DE RECEPCIÓN\n\nFecha: ".now()->format('d/m/Y').
            "\nRecibido por: {$creator->name}\n\nSe recibe documentación correspondiente a:\n".
            "- Facturas del mes\n- Correspondencia interna\n- Documentos de proveedores\n\n".
            'Firma: ________________________';

        $filename = 'documents/seed/acta-recepcion-'.uniqid().'.txt';
        Storage::disk('public')->put($filename, $content);

        Document::firstOrCreate([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'title' => 'Acta de Recepción - Documentos Varios',
        ], [
            'company_id' => $company->id,
            'branch_id' => $creator->branch_id,
            'department_id' => $creator->department_id,
            'category_id' => $this->categories[0]->id,
            'status_id' => $this->statuses[1]->id,
            'created_by' => $creator->id,
            'assigned_to' => $creator->id,
            'title' => 'Acta de Recepción - Documentos Varios',
            'description' => 'Acta de recepción de documentación entrante',
            'content' => $content,
            'file_path' => $filename,
            'priority' => 'low',
            'metadata' => [
                'file_name' => basename($filename),
                'mime_type' => 'text/plain',
                'seeder' => 'ComprehensiveTestDataSeeder',
            ],
        ]);
    }

    private function generateHtmlDocument(Company $company): void
    {
        $creator = $this->users[Role::Admin->value];
        $html = '<h1>Informe Mensual de Gestión</h1>'.
            '<p>Periodo: '.now()->subMonth()->format('F Y').'</p>'.
            '<h2>Resumen Ejecutivo</h2>'.
            '<p>Durante el período reportado se procesaron un total de 150 documentos, '.
            'de los cuales 120 fueron aprobados satisfactoriamente.</p>'.
            '<h2>Indicadores Clave</h2>'.
            '<ul><li>Documentos procesados: 150</li>'.
            '<li>Tasa de aprobación: 80%</li>'.
            '<li>Tiempo promedio de procesamiento: 2.5 días</li></ul>';

        $filename = 'documents/seed/informe-mensual-'.uniqid().'.html';
        Storage::disk('public')->put($filename, $html);

        Document::firstOrCreate([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'title' => 'Informe Mensual de Gestión Documental',
        ], [
            'company_id' => $company->id,
            'branch_id' => $creator->branch_id,
            'department_id' => $creator->department_id,
            'category_id' => $this->categories[5]->id ?? $this->categories[0]->id,
            'status_id' => $this->statuses[4]->id,
            'created_by' => $creator->id,
            'assigned_to' => $creator->id,
            'title' => 'Informe Mensual de Gestión Documental',
            'description' => 'Informe consolidado de gestión documental del mes anterior',
            'content' => strip_tags($html),
            'file_path' => $filename,
            'priority' => 'medium',
            'metadata' => [
                'file_name' => basename($filename),
                'mime_type' => 'text/html',
                'seeder' => 'ComprehensiveTestDataSeeder',
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function getDocumentSpecs(): array
    {
        return [
            // SuperAdmin documents
            [
                'title' => 'Política de Seguridad de la Información v2.0',
                'description' => 'Documento maestro de política de seguridad informática',
                'content' => 'Esta política establece los lineamientos generales para la protección de la información '.
                    'de ArchiveMaster Corp. Aplica a todos los empleados, contratistas y terceros que tengan acceso '.
                    'a los sistemas de información de la empresa. Se definen controles de acceso, clasificación de '.
                    'información, gestión de incidentes y responsabilidades de cada área.',
                'creator_role' => Role::SuperAdmin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'high',
                'confidential' => true,
            ],
            [
                'title' => 'Plan Estratégico 2026',
                'description' => 'Plan estratégico de la empresa para el año 2026',
                'content' => 'El plan estratégico contempla la expansión de operaciones a 3 nuevas ciudades, '.
                    'la implementación de un sistema de gestión documental avanzado con IA, y la certificación '.
                    'ISO 27001. Se proyecta un crecimiento del 25% en la base de clientes.',
                'creator_role' => Role::SuperAdmin->value,
                'status_index' => 3, // En Revisión
                'priority' => 'high',
                'confidential' => true,
            ],

            // Admin documents
            [
                'title' => 'Manual de Procedimientos Administrativos',
                'description' => 'Manual actualizado de procedimientos internos',
                'content' => 'Este manual describe los procedimientos estándar para la gestión administrativa '.
                    'de la empresa, incluyendo: gestión de correspondencia, archivo de documentos, '.
                    'flujos de aprobación, manejo de documentos confidenciales y protocolos de acceso.',
                'creator_role' => Role::Admin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'medium',
            ],
            [
                'title' => 'Reglamento Interno de Trabajo',
                'description' => 'Reglamento interno actualizado',
                'content' => 'Se establecen las normas de conducta, horarios, permisos, vacaciones, '.
                    'sanciones disciplinarias y demás disposiciones que rigen la relación laboral entre '.
                    'la empresa y sus trabajadores.',
                'creator_role' => Role::Admin->value,
                'assignee_role' => Role::OfficeManager->value,
                'status_index' => 2, // En Proceso
                'priority' => 'medium',
            ],

            // BranchAdmin documents
            [
                'title' => 'Inventario Sucursal Norte - Enero 2026',
                'description' => 'Inventario mensual de documentos en custodia',
                'content' => 'Inventario físico de documentos en custodia de la Sucursal Norte. Total de '.
                    'documentos: 1,247. Documentos en buen estado: 1,200. Documentos para digitalizar: 47. '.
                    'Se requiere adquisición de 3 cajas de archivo adicionales.',
                'creator_role' => Role::BranchAdmin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'medium',
            ],
            [
                'title' => 'Solicitud de Personal - Sucursal Norte',
                'description' => 'Solicitud de contratación de nuevo personal',
                'content' => 'Se solicita la contratación de 2 auxiliares de archivo para la Sucursal Norte '.
                    'debido al incremento en el volumen de documentación recibida. Se adjunta análisis '.
                    'de carga laboral y justificación presupuestal.',
                'creator_role' => Role::BranchAdmin->value,
                'assignee_role' => Role::Admin->value,
                'status_index' => 3, // En Revisión
                'priority' => 'high',
            ],

            // OfficeManager documents
            [
                'title' => 'Evaluación de Desempeño Q4 2025',
                'description' => 'Evaluaciones de desempeño del último trimestre',
                'content' => 'Resultados consolidados de evaluación de desempeño del departamento de RRHH. '.
                    'Promedio general: 4.2/5.0. Áreas de mejora identificadas: comunicación interna y '.
                    'gestión del tiempo. Se proponen capacitaciones para Q1 2026.',
                'creator_role' => Role::OfficeManager->value,
                'status_index' => 4, // Aprobado
                'priority' => 'medium',
                'confidential' => true,
            ],
            [
                'title' => 'Solicitud de Vacaciones - Febrero 2026',
                'description' => 'Consolidado de solicitudes de vacaciones',
                'content' => 'Se consolidan las solicitudes de vacaciones del personal del departamento de RRHH. '.
                    'Total solicitudes: 5. Aprobadas: 3. Pendientes de aprobación: 2. '.
                    'Se requiere coordinación con el departamento de Administración.',
                'creator_role' => Role::OfficeManager->value,
                'assignee_role' => Role::Admin->value,
                'status_index' => 2, // En Proceso
                'priority' => 'low',
            ],

            // ArchiveManager documents
            [
                'title' => 'Tabla de Retención Documental 2026',
                'description' => 'TRD actualizada para el período 2026',
                'content' => 'Se actualiza la Tabla de Retención Documental conforme a la normativa vigente. '.
                    'Se incluyen nuevas series documentales relacionadas con gestión de IA y documentos digitales. '.
                    'Tiempo de retención promedio: 10 años para documentos contables, 5 años para correspondencia.',
                'creator_role' => Role::ArchiveManager->value,
                'status_index' => 3, // En Revisión
                'priority' => 'high',
            ],
            [
                'title' => 'Acta de Eliminación Documental Nº 001-2026',
                'description' => 'Acta de eliminación de documentos vencidos',
                'content' => 'Acta de eliminación de documentos que cumplieron su tiempo de retención. '.
                    'Total documentos eliminados: 342. Procedimiento realizado conforme al protocolo '.
                    'de eliminación segura. Testigos: Fernando Archivo y Carlos Admin.',
                'creator_role' => Role::ArchiveManager->value,
                'assignee_role' => Role::Admin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'medium',
            ],

            // Receptionist documents
            [
                'title' => 'Correspondencia Recibida - 20/02/2026',
                'description' => 'Registro de correspondencia del día',
                'content' => 'Se registra la correspondencia recibida el día de hoy. Total de piezas: 15. '.
                    'Desglose: 8 facturas de proveedores, 3 comunicaciones oficiales, 2 invitaciones, '.
                    '1 notificación judicial, 1 paquete certificado.',
                'creator_role' => Role::Receptionist->value,
                'status_index' => 1, // Recibido
                'priority' => 'medium',
            ],
            [
                'title' => 'Factura Proveedor TechSolutions #F-2026-0234',
                'description' => 'Factura de servicios de tecnología',
                'content' => 'Factura por concepto de mantenimiento de servidores y licenciamiento de software. '.
                    'Valor: $5.400.000 COP. Fecha de vencimiento: 15/03/2026. Proveedor: TechSolutions S.A.S.',
                'creator_role' => Role::Receptionist->value,
                'assignee_role' => Role::OfficeManager->value,
                'status_index' => 2, // En Proceso
                'priority' => 'high',
            ],

            // RegularUser documents
            [
                'title' => 'Solicitud de Anticipo - Hugo Usuario',
                'description' => 'Solicitud de anticipo de nómina',
                'content' => 'Solicito un anticipo de nómina por el valor de $1.500.000 COP para cubrir gastos '.
                    'médicos urgentes. Adjunto soporte médico y compromiso de descuento en la nómina del mes '.
                    'de marzo 2026.',
                'creator_role' => Role::RegularUser->value,
                'assignee_role' => Role::OfficeManager->value,
                'status_index' => 0, // Borrador
                'priority' => 'medium',
            ],
            [
                'title' => 'Informe de Gastos Enero 2026',
                'description' => 'Informe mensual de gastos del departamento de Contabilidad',
                'content' => 'Informe consolidado de gastos del mes de enero 2026 para el departamento de '.
                    'Contabilidad. Total gastos: $12.350.000 COP. Presupuesto asignado: $15.000.000 COP. '.
                    'Ejecución presupuestal: 82.3%.',
                'creator_role' => Role::RegularUser->value,
                'status_index' => 1, // Recibido
                'priority' => 'low',
            ],

            // Extra cross-role documents for workflow testing
            [
                'title' => 'Contrato de Arrendamiento Bodega Sur',
                'description' => 'Contrato de arrendamiento para nueva bodega',
                'content' => 'Contrato de arrendamiento celebrado entre ArchiveMaster Corp y Inmobiliaria '.
                    'del Valle S.A.S. para el uso de bodega ubicada en la zona industrial de Cali. '.
                    'Canon mensual: $8.000.000 COP. Vigencia: 3 años.',
                'creator_role' => Role::Admin->value,
                'assignee_role' => Role::BranchAdmin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'high',
                'confidential' => true,
            ],
            [
                'title' => 'Circular Interna - Nuevo Horario de Atención',
                'description' => 'Comunicación interna sobre cambio de horarios',
                'content' => 'Se informa a todos los colaboradores que a partir del 1 de marzo de 2026, '.
                    'el horario de atención al público será de 8:00 AM a 5:00 PM. El horario de '.
                    'recepción de documentos será de 8:00 AM a 4:30 PM.',
                'creator_role' => Role::Admin->value,
                'status_index' => 4, // Aprobado
                'priority' => 'low',
            ],
        ];
    }
}
