<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\WorkflowDefinition;
use App\Models\Status;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ApprovalTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $approver;
    protected $document;
    protected $approval;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa y estados
        $this->company = Company::factory()->create();

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
        ]);

        $this->approver = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'approver@test.com',
        ]);

        // Crear documento
        $this->document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $statusPending->id,
            'created_by' => $creator->id,
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
    public function testUserCanViewPendingApprovals()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals')
                    ->assertStatus(200);

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
    public function testUserCanViewApprovalDetail()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals/document/' . $this->document->id)
                    ->assertSee($this->document->title)
                    ->assertSee('Aprobar Documento')
                    ->assertSee('Rechazar Documento');
        });
    }

    /**
     * Test que el usuario puede aprobar un documento
     */
    public function testUserCanApproveDocument()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals/document/' . $this->document->id)
                    ->click('[x-on:click="action = \'approve\'"]')
                    ->waitFor('textarea[name="comments"]')
                    ->type('textarea[name="comments"]', 'Aprobado correctamente')
                    ->press('Confirmar Aprobación')
                    ->waitForLocation('/approvals')
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
    public function testUserCanRejectDocument()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals/document/' . $this->document->id)
                    ->click('[x-on:click="action = \'reject\'"]')
                    ->waitFor('textarea[name="comments"]')
                    ->type('textarea[name="comments"]', 'Documentación incompleta')
                    ->press('Confirmar Rechazo')
                    ->waitForLocation('/approvals')
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
    public function testRejectRequiresComments()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals/document/' . $this->document->id)
                    ->click('[x-on:click="action = \'reject\'"]')
                    ->waitFor('textarea[name="comments"]')
                    ->press('Confirmar Rechazo')
                    ->assertPresent('textarea[name="comments"]:invalid');
        });
    }

    /**
     * Test que usuario no autorizado no puede aprobar
     */
    public function testUnauthorizedUserCannotApprove()
    {
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'other@test.com',
        ]);

        $this->browse(function (Browser $browser) use ($otherUser) {
            $browser->loginAs($otherUser)
                    ->visit('/approvals/document/' . $this->document->id)
                    ->assertSee('No tienes permisos para aprobar este documento');
        });
    }

    /**
     * Test que se puede ver historial de aprobaciones
     */
    public function testUserCanViewApprovalHistory()
    {
        // Crear historial de aprobaciones
        $this->approval->update([
            'status' => 'approved',
            'comments' => 'Aprobado en test',
            'responded_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->approver)
                    ->visit('/approvals/document/' . $this->document->id . '/history')
                    ->assertSee('Historial de Aprobaciones')
                    ->assertSee($this->document->title)
                    ->assertSee('Aprobado en test')
                    ->assertSee($this->approver->name);
        });
    }

    /**
     * Test que aprobación desaparece de la lista después de aprobar
     */
    public function testApprovalDisappearsAfterApproving()
    {
        $this->browse(function (Browser $browser) {
            // Ver que existe
            $browser->loginAs($this->approver)
                    ->visit('/approvals')
                    ->assertSee($this->document->title);

            // Aprobar
            $browser->visit('/approvals/document/' . $this->document->id)
                    ->click('[x-on:click="action = \'approve\'"]')
                    ->waitFor('textarea[name="comments"]')
                    ->press('Confirmar Aprobación')
                    ->waitForLocation('/approvals');

            // Verificar que ya no aparece
            $browser->visit('/approvals')
                    ->assertDontSee($this->document->title);
        });
    }
}
