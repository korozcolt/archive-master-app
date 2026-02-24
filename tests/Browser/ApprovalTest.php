<?php

namespace Tests\Browser;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class ApprovalTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $approver;

    protected $document;

    protected $approval;

    protected $company;

    protected $branch;

    protected $department;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa y estados
        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->department = Department::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
        $category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $statusPending = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Pendiente',
        ]);

        $statusApproved = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Aprobado',
        ]);

        // Crear workflow
        $workflow = WorkflowDefinition::factory()->create([
            'company_id' => $this->company->id,
            'from_status_id' => $statusPending->id,
            'to_status_id' => $statusApproved->id,
            'requires_approval' => true,
        ]);

        // Crear usuarios
        $creator = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ]);

        $this->approver = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'approver@test.com',
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ]);

        $approverRole = Role::firstOrCreate(['name' => 'office_manager']);
        $this->approver->assignRole($approverRole);

        // Crear documento
        $this->document = Document::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'category_id' => $category->id,
            'status_id' => $statusPending->id,
            'created_by' => $creator->id,
            'assigned_to' => $this->approver->id,
            'title' => 'Documento para aprobar',
        ]);

        // Crear aprobación pendiente
        $this->approval = DocumentApproval::create([
            'document_id' => $this->document->id,
            'workflow_definition_id' => $workflow->id,
            'approver_id' => $this->approver->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test que el usuario puede ver aprobaciones pendientes
     */
    public function test_user_can_view_pending_approvals()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                ->visit('/approvals')
                ->assertSee('Aprobaciones Pendientes');

            // Verificar en la base de datos
            $this->assertDatabaseHas('document_approvals', [
                'document_id' => $this->document->id,
                'approver_id' => $this->approver->id,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Test que el usuario puede ver detalle de documento para aprobar
     */
    public function test_user_can_view_approval_detail()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                ->visit('/approvals/document/'.$this->document->id)
                ->assertSee($this->document->title)
                ->assertSourceHas('Aprobar Documento')
                ->assertSourceHas('Rechazar Documento');
        });
    }

    /**
     * Test que el usuario puede aprobar un documento
     */
    public function test_user_can_approve_document()
    {
        $this->browse(function (Browser $browser) {
            $approvalId = $this->approval->id;

            $browser->loginAs($this->approver)
                ->visit('/approvals/document/'.$this->document->id)
                ->script("
                    const form = document.querySelector(`form[action$=\"/approvals/{$approvalId}/approve\"]`);
                    form.querySelector('textarea[name=\"comments\"]').value = 'Aprobado correctamente';
                    form.submit();
                ");

            $browser->waitForLocation('/approvals')
                ->assertSee('Documento aprobado correctamente');

            // Verificar en base de datos
            $this->approval->refresh();
            $this->assertEquals('approved', $this->approval->status);
            $this->assertEquals('Aprobado correctamente', $this->approval->comments);
            $this->assertNotNull($this->approval->responded_at);
        });
    }

    /**
     * Test que el usuario puede rechazar un documento
     */
    public function test_user_can_reject_document()
    {
        $this->browse(function (Browser $browser) {
            $approvalId = $this->approval->id;

            $browser->loginAs($this->approver)
                ->visit('/approvals/document/'.$this->document->id)
                ->script("
                    const form = document.querySelector(`form[action$=\"/approvals/{$approvalId}/reject\"]`);
                    form.querySelector('textarea[name=\"comments\"]').value = 'Documentación incompleta';
                    form.submit();
                ");

            $browser->waitForLocation('/approvals')
                ->assertSee('Documento rechazado correctamente');

            // Verificar en base de datos
            $this->approval->refresh();
            $this->assertEquals('rejected', $this->approval->status);
            $this->assertStringContainsString('Documentación incompleta', $this->approval->comments);
        });
    }

    /**
     * Test que rechazar requiere comentarios obligatorios
     */
    public function test_reject_requires_comments()
    {
        $this->browse(function (Browser $browser) {
            $approvalId = $this->approval->id;

            $browser->loginAs($this->approver)
                ->visit('/approvals/document/'.$this->document->id)
                ->script("
                    const form = document.querySelector(`form[action$=\"/approvals/{$approvalId}/reject\"]`);
                    form.submit();
                ");

            $browser->pause(500)
                ->assertPathIs('/approvals/document/'.$this->document->id);

            $this->approval->refresh();
            $this->assertEquals('pending', $this->approval->status);
        });
    }

    /**
     * Test que usuario no autorizado no puede aprobar
     */
    public function test_unauthorized_user_cannot_approve()
    {
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'other@test.com',
        ]);

        $otherRole = Role::firstOrCreate(['name' => 'office_manager']);
        $otherUser->assignRole($otherRole);

        $this->browse(function (Browser $browser) use ($otherUser) {
            $browser->loginAs($otherUser)
                ->visit('/approvals/document/'.$this->document->id)
                ->assertSee('403');
        });
    }

    /**
     * Test que se puede ver historial de aprobaciones
     */
    public function test_user_can_view_approval_history()
    {
        // Crear historial de aprobaciones
        $this->approval->update([
            'status' => 'approved',
            'comments' => 'Aprobado en test',
            'responded_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                ->visit('/approvals/document/'.$this->document->id.'/history')
                ->assertSee('Historial de Aprobaciones')
                ->assertSee($this->document->title)
                ->assertSee('Aprobado en test')
                ->assertSee($this->approver->name);
        });
    }

    /**
     * Test que aprobación desaparece de la lista después de aprobar
     */
    public function test_approval_disappears_after_approving()
    {
        $this->browse(function (Browser $browser) {
            $approvalId = $this->approval->id;

            // Ver que existe
            $browser->loginAs($this->approver)
                ->visit('/approvals')
                ->assertSee($this->document->title);

            // Aprobar
            $browser->visit('/approvals/document/'.$this->document->id)
                ->script("
                    const form = document.querySelector(`form[action$=\"/approvals/{$approvalId}/approve\"]`);
                    form.submit();
                ");

            $browser->waitForLocation('/approvals');

            // Verificar que ya no aparece
            $browser->visit('/approvals')
                ->assertDontSee($this->document->title);
        });
    }
}
