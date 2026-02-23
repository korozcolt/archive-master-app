<?php

namespace App\Console\Commands;

use App\Enums\Role as AppRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;
use App\Models\Receipt;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class SetupQaRegressionData extends Command
{
    protected $signature = 'app:setup-qa-regression-data {--password=Laboral2026!}';

    protected $description = 'Configura un dataset QA realista con credenciales persistentes por rol';

    public function handle(): int
    {
        $password = (string) $this->option('password');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createRolesAndPermissions();
        $company = $this->upsertCompany();
        $branches = $this->upsertBranches($company);
        $departments = $this->upsertDepartments($company, $branches);
        $statuses = $this->upsertStatuses($company);
        $category = $this->upsertCategory($company);
        $users = $this->upsertUsers($company, $branches, $departments, $password);
        $documents = $this->upsertDocuments($company, $category, $statuses, $users);
        $this->upsertApprovals($company, $statuses, $documents, $users);
        $this->upsertReceipt($company, $documents, $users);
        $this->upsertAiSettings($company);

        $this->line('');
        $this->info('Dataset QA listo.');
        $this->table(
            ['Rol', 'Nombre', 'Correo', 'Password', 'Acceso'],
            [
                ['super_admin', $users['super_admin']->name, $users['super_admin']->email, $password, '/admin/login'],
                ['admin', $users['admin']->name, $users['admin']->email, $password, '/admin/login'],
                ['branch_admin', $users['branch_admin']->name, $users['branch_admin']->email, $password, '/admin/login'],
                ['office_manager', $users['office_manager']->name, $users['office_manager']->email, $password, '/portal'],
                ['archive_manager', $users['archive_manager']->name, $users['archive_manager']->email, $password, '/portal'],
                ['receptionist', $users['receptionist']->name, $users['receptionist']->email, $password, '/portal'],
                ['regular_user', $users['regular_user']->name, $users['regular_user']->email, $password, '/portal'],
            ]
        );

        $this->line('Recibo QA: '.$documents['receipt']->document_number.' -> '.$users['regular_user']->email);
        $this->line('Aprobación QA pendiente: '.$documents['approval_pending']->document_number.' -> '.$users['office_manager']->email);

        return self::SUCCESS;
    }

    private function createRolesAndPermissions(): void
    {
        foreach (AppRole::cases() as $role) {
            SpatieRole::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        foreach (AppRole::getAllPermissions() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach (AppRole::cases() as $role) {
            $roleModel = SpatieRole::query()
                ->where('name', $role->value)
                ->where('guard_name', 'web')
                ->firstOrFail();

            $permissions = $role->getPermissions();
            if ($permissions === ['*']) {
                $roleModel->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

                continue;
            }

            $roleModel->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', $permissions)
                    ->get()
            );
        }
    }

    private function upsertCompany(): Company
    {
        return Company::query()->updateOrCreate(
            ['name' => 'ArchiveMaster QA'],
            [
                'legal_name' => 'ArchiveMaster QA S.A.S.',
                'tax_id' => '900999888-1',
                'address' => 'Avenida QA 123',
                'phone' => '+57 601 5550000',
                'email' => 'qa@archivemaster.test',
                'website' => 'https://archive-master-app.test',
                'primary_color' => '#1f4d8f',
                'secondary_color' => '#16a085',
                'active' => true,
            ]
        );
    }

    private function upsertBranches(Company $company): array
    {
        $hq = Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'QA-HQ'],
            ['name' => 'Sede QA Principal', 'city' => 'Bogota', 'country' => 'Colombia', 'active' => true]
        );

        $north = Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'QA-N'],
            ['name' => 'Sede QA Norte', 'city' => 'Medellin', 'country' => 'Colombia', 'active' => true]
        );

        return ['hq' => $hq, 'north' => $north];
    }

    private function upsertDepartments(Company $company, array $branches): array
    {
        $admin = Department::query()->updateOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches['hq']->id, 'code' => 'QA-ADMIN'],
            ['name' => 'Administracion QA', 'active' => true]
        );

        $operations = Department::query()->updateOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches['hq']->id, 'code' => 'QA-OPS'],
            ['name' => 'Operaciones QA', 'active' => true]
        );

        $archive = Department::query()->updateOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches['hq']->id, 'code' => 'QA-ARCH'],
            ['name' => 'Archivo QA', 'active' => true]
        );

        $service = Department::query()->updateOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches['north']->id, 'code' => 'QA-SVC'],
            ['name' => 'Servicio QA', 'active' => true]
        );

        return [
            'admin' => $admin,
            'operations' => $operations,
            'archive' => $archive,
            'service' => $service,
        ];
    }

    private function upsertStatuses(Company $company): array
    {
        $pending = Status::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'pending'],
            ['name' => 'Pendiente', 'order' => 1, 'is_initial' => true, 'is_final' => false, 'active' => true]
        );

        $inProgress = Status::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'in-progress'],
            ['name' => 'En Proceso', 'order' => 2, 'is_initial' => false, 'is_final' => false, 'active' => true]
        );

        $approved = Status::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'approved'],
            ['name' => 'Aprobado', 'order' => 3, 'is_initial' => false, 'is_final' => true, 'active' => true]
        );

        $rejected = Status::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'rejected'],
            ['name' => 'Rechazado', 'order' => 4, 'is_initial' => false, 'is_final' => true, 'active' => true]
        );

        return [
            'pending' => $pending,
            'in_progress' => $inProgress,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    private function upsertCategory(Company $company): Category
    {
        return Category::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'qa-operativo'],
            ['name' => 'QA Operativo', 'description' => 'Categoria base para regresion', 'active' => true, 'order' => 1]
        );
    }

    private function upsertUsers(Company $company, array $branches, array $departments, string $password): array
    {
        $users = [
            'super_admin' => [
                'name' => 'QA Super Admin',
                'email' => 'qa.superadmin@archivemaster.test',
                'role' => AppRole::SuperAdmin->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['admin']->id,
                'position' => 'Super Administrador',
            ],
            'admin' => [
                'name' => 'QA Admin',
                'email' => 'qa.admin@archivemaster.test',
                'role' => AppRole::Admin->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['admin']->id,
                'position' => 'Administrador',
            ],
            'branch_admin' => [
                'name' => 'QA Branch Admin',
                'email' => 'qa.branch@archivemaster.test',
                'role' => AppRole::BranchAdmin->value,
                'branch_id' => $branches['north']->id,
                'department_id' => $departments['service']->id,
                'position' => 'Administrador de Sucursal',
            ],
            'office_manager' => [
                'name' => 'QA Office Manager',
                'email' => 'qa.office@archivemaster.test',
                'role' => AppRole::OfficeManager->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['operations']->id,
                'position' => 'Encargado de Oficina',
            ],
            'archive_manager' => [
                'name' => 'QA Archive Manager',
                'email' => 'qa.archive@archivemaster.test',
                'role' => AppRole::ArchiveManager->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['archive']->id,
                'position' => 'Encargado de Archivo',
            ],
            'receptionist' => [
                'name' => 'QA Receptionist',
                'email' => 'qa.reception@archivemaster.test',
                'role' => AppRole::Receptionist->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['operations']->id,
                'position' => 'Recepcionista',
            ],
            'regular_user' => [
                'name' => 'QA Regular User',
                'email' => 'qa.user@archivemaster.test',
                'role' => AppRole::RegularUser->value,
                'branch_id' => $branches['hq']->id,
                'department_id' => $departments['operations']->id,
                'position' => 'Usuario Portal',
            ],
        ];

        $created = [];
        foreach ($users as $key => $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'company_id' => $company->id,
                    'branch_id' => $data['branch_id'],
                    'department_id' => $data['department_id'],
                    'position' => $data['position'],
                    'phone' => '+57 300 0000000',
                    'language' => 'es',
                    'timezone' => 'America/Bogota',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$data['role']]);
            $created[$key] = $user->fresh();
        }

        return $created;
    }

    private function upsertDocuments(Company $company, Category $category, array $statuses, array $users): array
    {
        $documents = [
            'receipt' => [
                'number' => 'QA-REC-0001',
                'title' => 'Radicado recepcion QA',
                'description' => 'Documento de entrada para flujo de recepcion y recibido.',
                'status_id' => $statuses['pending']->id,
                'created_by' => $users['receptionist']->id,
                'assigned_to' => $users['office_manager']->id,
                'priority' => 'medium',
            ],
            'approval_pending' => [
                'number' => 'QA-APR-0001',
                'title' => 'Aprobacion pendiente QA',
                'description' => 'Documento listo para pruebas de aprobacion.',
                'status_id' => $statuses['pending']->id,
                'created_by' => $users['receptionist']->id,
                'assigned_to' => $users['office_manager']->id,
                'priority' => 'high',
            ],
            'office_to_archive' => [
                'number' => 'QA-OFF-0001',
                'title' => 'Transferencia a archivo QA',
                'description' => 'Documento en proceso de transferencia a archivo.',
                'status_id' => $statuses['in_progress']->id,
                'created_by' => $users['office_manager']->id,
                'assigned_to' => $users['archive_manager']->id,
                'priority' => 'medium',
            ],
            'regular_assigned' => [
                'number' => 'QA-USER-0001',
                'title' => 'Documento asignado a usuario portal',
                'description' => 'Documento para validar vista de regular_user.',
                'status_id' => $statuses['in_progress']->id,
                'created_by' => $users['receptionist']->id,
                'assigned_to' => $users['regular_user']->id,
                'priority' => 'low',
            ],
            'approved' => [
                'number' => 'QA-DONE-0001',
                'title' => 'Documento aprobado QA',
                'description' => 'Documento historico aprobado para reportes.',
                'status_id' => $statuses['approved']->id,
                'created_by' => $users['office_manager']->id,
                'assigned_to' => $users['archive_manager']->id,
                'priority' => 'medium',
            ],
        ];

        $result = [];
        foreach ($documents as $key => $data) {
            $document = Document::query()->updateOrCreate(
                ['document_number' => $data['number']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $users['office_manager']->branch_id,
                    'department_id' => $users['office_manager']->department_id,
                    'category_id' => $category->id,
                    'status_id' => $data['status_id'],
                    'created_by' => $data['created_by'],
                    'assigned_to' => $data['assigned_to'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'priority' => $data['priority'],
                    'is_confidential' => false,
                    'tracking_enabled' => true,
                    'public_tracking_code' => Str::upper(Str::random(10)),
                    'received_at' => now()->subDays(2),
                    'due_at' => now()->addDays(5),
                    'metadata' => ['source' => 'qa_regression'],
                ]
            );

            DocumentVersion::query()->updateOrCreate(
                ['document_id' => $document->id, 'version_number' => 1],
                [
                    'created_by' => $data['created_by'],
                    'content' => 'Contenido de prueba QA para '.$document->document_number,
                    'file_name' => $document->document_number.'.txt',
                    'file_type' => 'text/plain',
                    'file_size' => 256,
                    'is_current' => true,
                    'change_summary' => 'Version inicial QA',
                    'metadata' => ['qa' => true],
                ]
            );

            $result[$key] = $document->fresh();
        }

        return $result;
    }

    private function upsertApprovals(Company $company, array $statuses, array $documents, array $users): void
    {
        $workflow = WorkflowDefinition::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'from_status_id' => $statuses['pending']->id,
                'to_status_id' => $statuses['approved']->id,
            ],
            [
                'name' => 'QA Pendiente -> Aprobado',
                'description' => 'Flujo base para aprobaciones QA',
                'roles_allowed' => [AppRole::OfficeManager->value, AppRole::Admin->value],
                'requires_approval' => true,
                'requires_comment' => false,
                'active' => true,
            ]
        );

        DocumentApproval::query()->updateOrCreate(
            [
                'document_id' => $documents['approval_pending']->id,
                'approver_id' => $users['office_manager']->id,
                'status' => 'pending',
            ],
            [
                'workflow_definition_id' => $workflow->id,
                'comments' => null,
                'responded_at' => null,
            ]
        );
    }

    private function upsertReceipt(Company $company, array $documents, array $users): void
    {
        Receipt::query()->updateOrCreate(
            ['receipt_number' => 'REC-QA-0001'],
            [
                'document_id' => $documents['receipt']->id,
                'company_id' => $company->id,
                'issued_by' => $users['receptionist']->id,
                'recipient_user_id' => $users['regular_user']->id,
                'recipient_name' => $users['regular_user']->name,
                'recipient_email' => $users['regular_user']->email,
                'recipient_phone' => $users['regular_user']->phone,
                'issued_at' => now(),
            ]
        );
    }

    private function upsertAiSettings(Company $company): void
    {
        CompanyAiSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'provider' => 'none',
                'is_enabled' => false,
                'monthly_budget_cents' => 0,
                'daily_doc_limit' => 100,
                'max_pages_per_doc' => 100,
                'store_outputs' => true,
                'redact_pii' => true,
                'api_key_encrypted' => null,
            ]
        );
    }
}
