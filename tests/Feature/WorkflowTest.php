<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistory;
use App\Models\Status;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $statusDraft;
    protected $statusReview;
    protected $statusApproved;
    protected $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Crear estados
        $this->statusDraft = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Borrador'],
        ]);

        $this->statusReview = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'En Revisión'],
        ]);

        $this->statusApproved = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => ['es' => 'Aprobado'],
        ]);

        // Crear workflow
        $this->workflow = WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Workflow de Prueba',
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
            'requires_approval' => false,
        ]);
    }

    /**
     * Test que se puede crear una transición de workflow
     */
    public function test_workflow_transition_can_be_created()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->statusDraft->id,
            'created_by' => $this->user->id,
        ]);

        // Cambiar estado del documento
        $document->status_id = $this->statusReview->id;
        $document->save();

        // Crear historial de workflow
        WorkflowHistory::create([
            'document_id' => $document->id,
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
            'performed_by' => $this->user->id,
            'comments' => 'Enviando a revisión',
        ]);

        $this->assertDatabaseHas('workflow_histories', [
            'document_id' => $document->id,
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
        ]);
    }

    /**
     * Test que se puede ver el historial de workflow de un documento
     */
    public function test_document_workflow_history_can_be_viewed()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->statusReview->id,
            'created_by' => $this->user->id,
        ]);

        // Crear historial
        WorkflowHistory::create([
            'document_id' => $document->id,
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
            'performed_by' => $this->user->id,
            'comments' => 'Primera transición',
        ]);

        $history = WorkflowHistory::where('document_id', $document->id)->get();

        $this->assertCount(1, $history);
        $this->assertEquals('Primera transición', $history->first()->comments);
    }

    /**
     * Test que un workflow puede requerir aprobación
     */
    public function test_workflow_can_require_approval()
    {
        $workflowWithApproval = WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'from_status_id' => $this->statusReview->id,
            'to_status_id' => $this->statusApproved->id,
            'requires_approval' => true,
        ]);

        $this->assertTrue($workflowWithApproval->requires_approval);
    }

    /**
     * Test que se pueden obtener workflows activos de una empresa
     */
    public function test_can_get_active_workflows_for_company()
    {
        WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'from_status_id' => $this->statusReview->id,
            'to_status_id' => $this->statusApproved->id,
            'active' => true,
        ]);

        WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusApproved->id,
            'active' => false,
        ]);

        $activeWorkflows = WorkflowDefinition::where('company_id', $this->company->id)
            ->where('active', true)
            ->get();

        $this->assertGreaterThanOrEqual(1, $activeWorkflows->count());
    }

    /**
     * Test que un workflow tiene relaciones correctas
     */
    public function test_workflow_has_correct_relationships()
    {
        $this->assertInstanceOf(Company::class, $this->workflow->company);
        $this->assertInstanceOf(Status::class, $this->workflow->fromStatus);
        $this->assertInstanceOf(Status::class, $this->workflow->toStatus);
    }

    /**
     * Test que se puede obtener la definición de workflow para una transición
     */
    public function test_can_get_workflow_definition_for_transition()
    {
        $definition = WorkflowDefinition::where('company_id', $this->company->id)
            ->where('from_status_id', $this->statusDraft->id)
            ->where('to_status_id', $this->statusReview->id)
            ->first();

        $this->assertNotNull($definition);
        $this->assertEquals($this->workflow->id, $definition->id);
    }

    /**
     * Test que el historial de workflow registra el usuario que realizó la acción
     */
    public function test_workflow_history_records_user()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->statusDraft->id,
            'created_by' => $this->user->id,
        ]);

        $history = WorkflowHistory::create([
            'document_id' => $document->id,
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
            'performed_by' => $this->user->id,
            'comments' => 'Test transition',
        ]);

        $this->assertEquals($this->user->id, $history->performed_by);
        $this->assertInstanceOf(User::class, $history->user);
    }

    /**
     * Test que un workflow puede tener configuración de aprobación
     */
    public function test_workflow_can_have_approval_config()
    {
        $workflowWithConfig = WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'from_status_id' => $this->statusReview->id,
            'to_status_id' => $this->statusApproved->id,
            'requires_approval' => true,
            'approval_config' => [
                'approvers' => [$this->user->id],
                'min_approvals' => 1,
            ],
        ]);

        $this->assertIsArray($workflowWithConfig->approval_config);
        $this->assertEquals(1, $workflowWithConfig->approval_config['min_approvals']);
    }

    /**
     * Test que no se pueden crear workflows duplicados
     */
    public function test_cannot_create_duplicate_workflows()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        WorkflowDefinition::create([
            'company_id' => $this->company->id,
            'name' => 'Duplicado',
            'from_status_id' => $this->statusDraft->id,
            'to_status_id' => $this->statusReview->id,
        ]);
    }
}
