<?php

namespace App\Filament\Resources\WorkflowDefinitionResource\Pages;

use App\Filament\Resources\WorkflowDefinitionResource;
use App\Models\Status;
use App\Models\WorkflowDefinition;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowDefinitions extends ListRecords
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('createCompleteWorkflow')
                ->label('Crear Flujo Completo')
                ->action(function (): void {
                    // Modal para seleccionar empresa y tipo de flujo
                    $this->mountAction('selectCompanyAndType');
                })
                ->icon('heroicon-o-bolt')
                ->color('success'),
        ];
    }

    // Acción para seleccionar empresa y tipo de flujo
    public function selectCompanyAndType(): array
    {
        return [
            'title' => 'Crear Flujo de Trabajo Completo',
            'description' => 'Seleccione la empresa y el tipo de flujo que desea crear.',
            'form' => [
                \Filament\Forms\Components\Select::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                \Filament\Forms\Components\Select::make('workflow_type')
                    ->label('Tipo de Flujo')
                    ->options([
                        'basic' => 'Flujo Básico (Recibido → Procesando → Revisión → Aprobado/Rechazado)',
                        'extended' => 'Flujo Extendido (incluye Correcciones, Firma y más estados)',
                    ])
                    ->required(),
            ],
            'action' => function (array $data): void {
                $companyId = $data['company_id'];
                $workflowType = $data['workflow_type'];

                // Determinar qué tipo de flujo crear
                if ($workflowType === 'basic') {
                    $this->createBasicWorkflow($companyId);
                } else {
                    $this->createExtendedWorkflow($companyId);
                }

                $this->notification()->success(
                    title: 'Flujo de trabajo creado',
                    body: 'El flujo de trabajo ha sido creado exitosamente.',
                );
            },
        ];
    }

    // Crear flujo de trabajo básico
    private function createBasicWorkflow(int $companyId): void
    {
        // Obtener estados necesarios
        $receivedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'received')
            ->first();

        $inProcessStatus = Status::where('company_id', $companyId)
            ->where('slug', 'in_process')
            ->first();

        $underReviewStatus = Status::where('company_id', $companyId)
            ->where('slug', 'under_review')
            ->first();

        $approvedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'approved')
            ->first();

        $rejectedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'rejected')
            ->first();

        $archivedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'archived')
            ->first();

        if (!$receivedStatus || !$inProcessStatus || !$underReviewStatus ||
            !$approvedStatus || !$rejectedStatus || !$archivedStatus) {
            $this->notification()->warning(
                title: 'Estados no encontrados',
                body: 'No se encontraron todos los estados necesarios para crear el flujo de trabajo. Verifique que todos los estados base existan.',
            );
            return;
        }

        // Crear transiciones
        $this->createTransition(
            $companyId,
            $receivedStatus->id,
            $inProcessStatus->id,
            'Iniciar Procesamiento',
            'Iniciar el procesamiento de un documento recibido',
            null, // cualquier rol
            false,
            false,
            24
        );

        $this->createTransition(
            $companyId,
            $inProcessStatus->id,
            $underReviewStatus->id,
            'Enviar a Revisión',
            'Enviar un documento procesado para su revisión',
            json_encode(['regular_user', 'office_manager']),
            false,
            true,
            48
        );

        $this->createTransition(
            $companyId,
            $underReviewStatus->id,
            $approvedStatus->id,
            'Aprobar Documento',
            'Aprobar un documento revisado',
            json_encode(['office_manager', 'branch_admin', 'admin']),
            true,
            false,
            24
        );

        $this->createTransition(
            $companyId,
            $underReviewStatus->id,
            $rejectedStatus->id,
            'Rechazar Documento',
            'Rechazar un documento revisado',
            json_encode(['office_manager', 'branch_admin', 'admin']),
            true,
            true,
            24
        );

        $this->createTransition(
            $companyId,
            $underReviewStatus->id,
            $inProcessStatus->id,
            'Devolver para Correcciones',
            'Devolver un documento para ajustes o correcciones',
            json_encode(['office_manager', 'branch_admin']),
            false,
            true,
            24
        );

        $this->createTransition(
            $companyId,
            $approvedStatus->id,
            $archivedStatus->id,
            'Archivar Documento Aprobado',
            'Archivar un documento que ha sido aprobado',
            json_encode(['archive_manager']),
            false,
            false,
            null
        );

        $this->createTransition(
            $companyId,
            $rejectedStatus->id,
            $archivedStatus->id,
            'Archivar Documento Rechazado',
            'Archivar un documento que ha sido rechazado',
            json_encode(['archive_manager']),
            false,
            false,
            null
        );
    }

    // Crear flujo de trabajo extendido
    private function createExtendedWorkflow(int $companyId): void
    {
        // Crear primero el flujo básico
        $this->createBasicWorkflow($companyId);

        // Obtener estados adicionales
        $inCorrectionsStatus = Status::where('company_id', $companyId)
            ->where('slug', 'in-corrections')
            ->first();

        $awaitingSignatureStatus = Status::where('company_id', $companyId)
            ->where('slug', 'awaiting-signature')
            ->first();

        $cancelledStatus = Status::where('company_id', $companyId)
            ->where('slug', 'cancelled')
            ->first();

        $replacedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'replaced')
            ->first();

        // También necesitamos algunos estados básicos
        $inProcessStatus = Status::where('company_id', $companyId)
            ->where('slug', 'in_process')
            ->first();

        $underReviewStatus = Status::where('company_id', $companyId)
            ->where('slug', 'under_review')
            ->first();

        $approvedStatus = Status::where('company_id', $companyId)
            ->where('slug', 'approved')
            ->first();

        // Verificar si se encontraron los estados adicionales necesarios
        if (!$inCorrectionsStatus || !$awaitingSignatureStatus ||
            !$cancelledStatus || !$replacedStatus) {
            $this->notification()->warning(
                title: 'Estados adicionales no encontrados',
                body: 'No se encontraron todos los estados adicionales necesarios para el flujo extendido. Se creó solo el flujo básico.',
            );
            return;
        }

        // Crear transiciones adicionales
        if ($inCorrectionsStatus && $underReviewStatus && $inProcessStatus) {
            $this->createTransition(
                $companyId,
                $inProcessStatus->id,
                $inCorrectionsStatus->id,
                'Enviar a Correcciones',
                'Enviar documento para correcciones',
                json_encode(['office_manager', 'branch_admin']),
                false,
                true,
                24
            );

            $this->createTransition(
                $companyId,
                $inCorrectionsStatus->id,
                $underReviewStatus->id,
                'Enviar Correcciones a Revisión',
                'Enviar documento corregido para revisión',
                json_encode(['regular_user', 'office_manager']),
                false,
                true,
                24
            );
        }

        if ($awaitingSignatureStatus && $approvedStatus && $underReviewStatus) {
            $this->createTransition(
                $companyId,
                $underReviewStatus->id,
                $awaitingSignatureStatus->id,
                'Enviar para Firma',
                'Enviar documento para firma',
                json_encode(['office_manager', 'branch_admin', 'admin']),
                true,
                false,
                24
            );

            $this->createTransition(
                $companyId,
                $awaitingSignatureStatus->id,
                $approvedStatus->id,
                'Confirmar Firma',
                'Confirmar que el documento ha sido firmado',
                json_encode(['office_manager', 'branch_admin', 'admin']),
                false,
                false,
                24
            );
        }

        // Más lógica para crear otras transiciones según sea necesario
    }

    // Método auxiliar para crear una transición
    private function createTransition(
        int $companyId,
        int $fromStatusId,
        int $toStatusId,
        string $name,
        string $description,
        ?string $rolesAllowed,
        bool $requiresApproval,
        bool $requiresComment,
        ?int $slaHours
    ): void {
        WorkflowDefinition::firstOrCreate(
            [
                'company_id' => $companyId,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
            ],
            [
                'name' => $name,
                'description' => $description,
                'roles_allowed' => $rolesAllowed,
                'requires_approval' => $requiresApproval,
                'requires_comment' => $requiresComment,
                'sla_hours' => $slaHours,
                'active' => true,
            ]
        );
    }
}
