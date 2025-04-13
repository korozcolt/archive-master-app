<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
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

        // Para cada empresa, crear definiciones de flujo de trabajo
        foreach ($companies as $company) {
            $this->command->info("Creando flujos de trabajo para la empresa: {$company->name}");

            $this->createDefaultWorkflows($company);
            $this->createExtendedWorkflows($company);
        }
    }

    /**
     * Crear flujos de trabajo predeterminados
     */
    private function createDefaultWorkflows(Company $company): void
    {
        // Obtener los estados de la empresa
        $statuses = Status::where('company_id', $company->id)
                        ->where('active', true)
                        ->get()
                        ->keyBy('slug');

        // Si no hay suficientes estados, no podemos crear workflows
        if (count($statuses) < 2) {
            $this->command->info("  - Insuficientes estados para crear flujos de trabajo");
            return;
        }

        $this->command->info("  - Creando flujos de trabajo básicos");

        // Flujo de trabajo básico: Recibido -> En Proceso -> En Revisión -> Aprobado/Rechazado
        $this->createBasicWorkflow($company, $statuses);

        // Flujo para borradores: Borrador -> Recibido
        if (isset($statuses[DocumentStatus::Draft->value]) && isset($statuses[DocumentStatus::Received->value])) {
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $statuses[DocumentStatus::Draft->value]->id,
                    'to_status_id' => $statuses[DocumentStatus::Received->value]->id,
                ],
                [
                    'name' => 'Enviar Borrador',
                    'description' => 'Enviar un documento borrador para su procesamiento',
                    'roles_allowed' => null, // Cualquier rol puede realizar esta transición
                    'requires_approval' => false,
                    'requires_comment' => false,
                    'active' => true,
                ]
            );
        }

        // Flujo de archivo: Aprobado/Rechazado -> Archivado
        if (isset($statuses[DocumentStatus::Approved->value]) && isset($statuses[DocumentStatus::Archived->value])) {
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $statuses[DocumentStatus::Approved->value]->id,
                    'to_status_id' => $statuses[DocumentStatus::Archived->value]->id,
                ],
                [
                    'name' => 'Archivar Documento Aprobado',
                    'description' => 'Archivar un documento que ha sido aprobado',
                    'roles_allowed' => json_encode([Role::ArchiveManager->value]),
                    'requires_approval' => false,
                    'requires_comment' => false,
                    'active' => true,
                ]
            );
        }

        if (isset($statuses[DocumentStatus::Rejected->value]) && isset($statuses[DocumentStatus::Archived->value])) {
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $statuses[DocumentStatus::Rejected->value]->id,
                    'to_status_id' => $statuses[DocumentStatus::Archived->value]->id,
                ],
                [
                    'name' => 'Archivar Documento Rechazado',
                    'description' => 'Archivar un documento que ha sido rechazado',
                    'roles_allowed' => json_encode([Role::ArchiveManager->value]),
                    'requires_approval' => false,
                    'requires_comment' => false,
                    'active' => true,
                ]
            );
        }
    }

    /**
     * Crear flujo de trabajo básico
     */
    private function createBasicWorkflow(Company $company, $statuses): void
    {
        // Definir las transiciones estándar del flujo
        $standardFlow = [
            [
                'from' => DocumentStatus::Received->value,
                'to' => DocumentStatus::InProcess->value,
                'name' => 'Iniciar Procesamiento',
                'description' => 'Iniciar el procesamiento de un documento recibido',
                'roles_allowed' => null, // Cualquier rol puede realizar esta transición
                'requires_approval' => false,
                'requires_comment' => false,
                'sla_hours' => 24,
            ],
            [
                'from' => DocumentStatus::InProcess->value,
                'to' => DocumentStatus::UnderReview->value,
                'name' => 'Enviar a Revisión',
                'description' => 'Enviar un documento procesado para su revisión',
                'roles_allowed' => json_encode([Role::RegularUser->value, Role::OfficeManager->value]),
                'requires_approval' => false,
                'requires_comment' => true,
                'sla_hours' => 48,
            ],
            [
                'from' => DocumentStatus::UnderReview->value,
                'to' => DocumentStatus::Approved->value,
                'name' => 'Aprobar Documento',
                'description' => 'Aprobar un documento revisado',
                'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value]),
                'requires_approval' => true,
                'requires_comment' => false,
                'sla_hours' => 24,
            ],
            [
                'from' => DocumentStatus::UnderReview->value,
                'to' => DocumentStatus::Rejected->value,
                'name' => 'Rechazar Documento',
                'description' => 'Rechazar un documento revisado',
                'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value]),
                'requires_approval' => true,
                'requires_comment' => true,
                'sla_hours' => 24,
            ],
            [
                'from' => DocumentStatus::UnderReview->value,
                'to' => DocumentStatus::InProcess->value,
                'name' => 'Devolver para Correcciones',
                'description' => 'Devolver un documento para ajustes o correcciones',
                'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value]),
                'requires_approval' => false,
                'requires_comment' => true,
                'sla_hours' => 24,
            ],
        ];

        // Crear cada transición
        foreach ($standardFlow as $transition) {
            // Verificar que existan los estados
            if (!isset($statuses[$transition['from']]) || !isset($statuses[$transition['to']])) {
                continue;
            }

            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $statuses[$transition['from']]->id,
                    'to_status_id' => $statuses[$transition['to']]->id,
                ],
                [
                    'name' => $transition['name'],
                    'description' => $transition['description'],
                    'roles_allowed' => $transition['roles_allowed'],
                    'requires_approval' => $transition['requires_approval'],
                    'requires_comment' => $transition['requires_comment'],
                    'sla_hours' => $transition['sla_hours'],
                    'active' => true,
                ]
            );
        }
    }

    /**
     * Crear flujos de trabajo extendidos
     */
    private function createExtendedWorkflows(Company $company): void
    {
        // Obtener todos los estados para la empresa, incluidos los adicionales
        $statuses = Status::where('company_id', $company->id)
                        ->where('active', true)
                        ->get();

        // Si no hay suficientes estados, no podemos crear workflows extendidos
        if ($statuses->count() < 5) {
            return;
        }

        $this->command->info("  - Creando flujos de trabajo extendidos");

        // Buscar estados específicos
        $draftStatus = $statuses->where('slug', DocumentStatus::Draft->value)->first();
        $receivedStatus = $statuses->where('slug', DocumentStatus::Received->value)->first();
        $inProcessStatus = $statuses->where('slug', DocumentStatus::InProcess->value)->first();
        $underReviewStatus = $statuses->where('slug', DocumentStatus::UnderReview->value)->first();
        $approvedStatus = $statuses->where('slug', DocumentStatus::Approved->value)->first();
        $rejectedStatus = $statuses->where('slug', DocumentStatus::Rejected->value)->first();
        $archivedStatus = $statuses->where('slug', DocumentStatus::Archived->value)->first();
        $expiredStatus = $statuses->where('slug', DocumentStatus::Expired->value)->first();

        // Estados adicionales
        $inCorrectionsStatus = $statuses->where('slug', 'in-corrections')->first();
        $awaitingSignatureStatus = $statuses->where('slug', 'awaiting-signature')->first();
        $cancelledStatus = $statuses->where('slug', 'cancelled')->first();
        $replacedStatus = $statuses->where('slug', 'replaced')->first();

        // Si existen los estados adicionales, crear transiciones específicas
        if ($inCorrectionsStatus && $underReviewStatus && $inProcessStatus) {
            // De En Proceso a En Correcciones
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $inProcessStatus->id,
                    'to_status_id' => $inCorrectionsStatus->id,
                ],
                [
                    'name' => 'Enviar a Correcciones',
                    'description' => 'Enviar documento para correcciones',
                    'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value]),
                    'requires_approval' => false,
                    'requires_comment' => true,
                    'sla_hours' => 24,
                    'active' => true,
                ]
            );

            // De En Correcciones a En Revisión
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $inCorrectionsStatus->id,
                    'to_status_id' => $underReviewStatus->id,
                ],
                [
                    'name' => 'Enviar Correcciones a Revisión',
                    'description' => 'Enviar documento corregido para revisión',
                    'roles_allowed' => json_encode([Role::RegularUser->value, Role::OfficeManager->value]),
                    'requires_approval' => false,
                    'requires_comment' => true,
                    'sla_hours' => 24,
                    'active' => true,
                ]
            );
        }

        if ($awaitingSignatureStatus && $approvedStatus && $underReviewStatus) {
            // De En Revisión a Esperando Firma
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $underReviewStatus->id,
                    'to_status_id' => $awaitingSignatureStatus->id,
                ],
                [
                    'name' => 'Enviar para Firma',
                    'description' => 'Enviar documento para firma',
                    'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value]),
                    'requires_approval' => true,
                    'requires_comment' => false,
                    'sla_hours' => 24,
                    'active' => true,
                ]
            );

            // De Esperando Firma a Aprobado
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $awaitingSignatureStatus->id,
                    'to_status_id' => $approvedStatus->id,
                ],
                [
                    'name' => 'Confirmar Firma',
                    'description' => 'Confirmar que el documento ha sido firmado',
                    'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value]),
                    'requires_approval' => false,
                    'requires_comment' => false,
                    'sla_hours' => 24,
                    'active' => true,
                ]
            );
        }

        if ($cancelledStatus) {
            // Transiciones a Cancelado desde varios estados
            $fromStatuses = [$draftStatus, $receivedStatus, $inProcessStatus, $underReviewStatus, $awaitingSignatureStatus];

            foreach ($fromStatuses as $fromStatus) {
                if ($fromStatus) {
                    WorkflowDefinition::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'from_status_id' => $fromStatus->id,
                            'to_status_id' => $cancelledStatus->id,
                        ],
                        [
                            'name' => "Cancelar Documento desde {$fromStatus->name}",
                            'description' => "Cancelar un documento que está en estado {$fromStatus->name}",
                            'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value]),
                            'requires_approval' => true,
                            'requires_comment' => true,
                            'sla_hours' => null,
                            'active' => true,
                        ]
                    );
                }
            }
        }

        if ($replacedStatus && $approvedStatus) {
            // De Aprobado a Reemplazado
            WorkflowDefinition::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'from_status_id' => $approvedStatus->id,
                    'to_status_id' => $replacedStatus->id,
                ],
                [
                    'name' => 'Marcar como Reemplazado',
                    'description' => 'Marcar un documento aprobado como reemplazado por una nueva versión',
                    'roles_allowed' => json_encode([Role::OfficeManager->value, Role::BranchAdmin->value, Role::Admin->value, Role::ArchiveManager->value]),
                    'requires_approval' => false,
                    'requires_comment' => true,
                    'sla_hours' => null,
                    'active' => true,
                ]
            );
        }
    }
}
