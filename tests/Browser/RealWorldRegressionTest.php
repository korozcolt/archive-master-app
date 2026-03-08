<?php

namespace Tests\Browser;

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentDistributionTarget;
use App\Models\PhysicalLocation;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RealWorldRegressionTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('app:setup-qa-regression-data');
    }

    public function test_admin_and_portal_role_routing_rules_hold(): void
    {
        $adminRoles = [
            'qa.superadmin@archivemaster.test',
            'qa.admin@archivemaster.test',
            'qa.branch@archivemaster.test',
        ];

        $portalRoles = [
            'qa.office@archivemaster.test',
            'qa.archive@archivemaster.test',
            'qa.reception@archivemaster.test',
            'qa.user@archivemaster.test',
        ];

        foreach ($adminRoles as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertPathBeginsWith('/admin');
            });
        }

        foreach ($portalRoles as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user)
                    ->visit('/admin');

                $currentPath = parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH);

                if ($currentPath === '/portal') {
                    $browser->assertPathIs('/portal');
                } else {
                    $browser->assertSee('PROHIBIDO');
                }

                $browser->visit('/portal')
                    ->assertPathIs('/portal');
            });
        }
    }

    public function test_approval_flow_is_visible_for_operational_user(): void
    {
        $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->firstOrFail();
        $document = \App\Models\Document::query()->where('document_number', 'QA-APR-0001')->firstOrFail();

        $this->browse(function (Browser $browser) use ($officeManager, $document) {
            $this->loginThroughPortal($browser, $officeManager->email)
                ->visit('/approvals')
                ->assertPathIs('/approvals');

            $approvalsIndexSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('Aprobaciones Pendientes', $approvalsIndexSource);
            $this->assertStringContainsString('QA-APR-0001', $approvalsIndexSource);

            $browser
                ->visit('/approvals/document/'.$document->id)
                ->assertPathIs('/approvals/document/'.$document->id);

            $approvalDetailSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('QA-APR-0001', $approvalDetailSource);
        });
    }

    public function test_regular_user_can_view_receipt_from_seeded_dataset(): void
    {
        $regularUser = User::query()->where('email', 'qa.user@archivemaster.test')->firstOrFail();
        $receipt = Receipt::query()->where('receipt_number', 'REC-QA-0001')->firstOrFail();

        $this->browse(function (Browser $browser) use ($regularUser, $receipt) {
            $this->loginThroughPortal($browser, $regularUser->email)
                ->visit('/receipts/'.$receipt->id)
                ->assertPathIs('/receipts/'.$receipt->id);

            $receiptSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($receipt->receipt_number, $receiptSource);
            $this->assertStringContainsString($regularUser->email, $receiptSource);
        });
    }

    public function test_receptionist_can_create_and_distribute_document_from_real_ui_flow(): void
    {
        $receptionist = User::query()->where('email', 'qa.reception@archivemaster.test')->firstOrFail();
        $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->firstOrFail();
        $documentTitle = 'Smoke UI '.str_replace('.', '', uniqid('', true));
        $documentDescription = 'Documento creado por wizard real para smoke multirol.';
        $routingNote = 'Enviar a oficina QA desde flujo real.';
        $createdDocumentId = null;

        $this->browse(function (Browser $browser) use ($receptionist, $officeManager, $documentTitle, $documentDescription, $routingNote, &$createdDocumentId) {
            $createdDocumentId = $this->createAndDistributeDocumentViaPortal(
                browser: $browser,
                receptionistEmail: $receptionist->email,
                officeDepartmentId: (int) $officeManager->department_id,
                documentTitle: $documentTitle,
                documentDescription: $documentDescription,
                routingNote: $routingNote,
                recipientName: 'QA Usuario Portal',
                recipientEmail: 'qa.user@archivemaster.test',
                recipientPhone: '3001234567',
            );
        });

        $createdDocument = Document::query()->findOrFail($createdDocumentId);
        $distributionTarget = DocumentDistributionTarget::query()
            ->whereHas('distribution', fn ($query) => $query->where('document_id', $createdDocument->id))
            ->where('department_id', $officeManager->department_id)
            ->firstOrFail();

        $this->assertSame($documentTitle, $createdDocument->title);
        $this->assertSame($receptionist->id, $createdDocument->created_by);
        $this->assertSame('sent', $distributionTarget->status);
        $this->assertSame($routingNote, $distributionTarget->routing_note);

        $this->browse(function (Browser $browser) use ($officeManager, $createdDocument, $distributionTarget, $documentTitle, $routingNote) {
            $this->loginThroughPortal($browser, $officeManager->email)
                ->visit('/documents/'.$createdDocument->id)
                ->assertPathIs('/documents/'.$createdDocument->id);

            $documentSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($documentTitle, $documentSource);
            $this->assertStringContainsString($routingNote, $documentSource);

            $browser->script(
                'document.querySelector(\'form[action="'.route('documents.distribution-targets.update', [$createdDocument, $distributionTarget]).'"]\').submit();'
            );

            $browser->waitForLocation('/documents/'.$createdDocument->id);

            $updatedSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($documentTitle, $updatedSource);
            $this->assertStringContainsString('Recibido', $updatedSource);
        });

        $distributionTarget->refresh();

        $this->assertSame('received', $distributionTarget->status);
        $this->assertSame($officeManager->id, $distributionTarget->last_updated_by);
        $this->assertNotNull($distributionTarget->received_at);
    }

    public function test_created_document_can_flow_from_reception_to_archive_and_final_user(): void
    {
        $receptionist = User::query()->where('email', 'qa.reception@archivemaster.test')->firstOrFail();
        $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->firstOrFail();
        $archiveManager = User::query()->where('email', 'qa.archive@archivemaster.test')->firstOrFail();
        $regularUser = User::query()->where('email', 'qa.user@archivemaster.test')->firstOrFail();
        $location = PhysicalLocation::query()
            ->where('company_id', $archiveManager->company_id)
            ->orderBy('id')
            ->firstOrFail();

        $documentTitle = 'Smoke Full UI '.str_replace('.', '', uniqid('', true));
        $documentDescription = 'Documento creado y recorrido por todos los roles operativos.';
        $routingNote = 'Enviar y cerrar flujo completo desde UI.';
        $createdDocumentId = null;

        $this->browse(function (Browser $browser) use ($receptionist, $officeManager, $documentTitle, $documentDescription, $routingNote, &$createdDocumentId) {
            $createdDocumentId = $this->createAndDistributeDocumentViaPortal(
                browser: $browser,
                receptionistEmail: $receptionist->email,
                officeDepartmentId: (int) $officeManager->department_id,
                documentTitle: $documentTitle,
                documentDescription: $documentDescription,
                routingNote: $routingNote,
                recipientName: 'QA Usuario Portal',
                recipientEmail: 'qa.user@archivemaster.test',
                recipientPhone: '3001234567',
            );
        });

        $createdDocument = Document::query()->findOrFail($createdDocumentId);
        $receipt = Receipt::query()->where('document_id', $createdDocument->id)->firstOrFail();
        $distributionTarget = DocumentDistributionTarget::query()
            ->whereHas('distribution', fn ($query) => $query->where('document_id', $createdDocument->id))
            ->where('department_id', $officeManager->department_id)
            ->firstOrFail();

        $this->browse(function (Browser $browser) use ($officeManager, $createdDocument, $distributionTarget, $documentTitle) {
            $this->loginThroughPortal($browser, $officeManager->email)
                ->visit('/documents/'.$createdDocument->id)
                ->assertPathIs('/documents/'.$createdDocument->id);

            $browser->script(
                'document.querySelector(\'form[action="'.route('documents.distribution-targets.update', [$createdDocument, $distributionTarget]).'"]\').submit();'
            );

            $browser->waitForLocation('/documents/'.$createdDocument->id);

            $updatedSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($documentTitle, $updatedSource);
            $this->assertStringContainsString('Recibido', $updatedSource);
        });

        $this->browse(function (Browser $browser) use ($archiveManager, $createdDocument, $location, $documentTitle) {
            $this->loginThroughPortal($browser, $archiveManager->email)
                ->visit('/documents/'.$createdDocument->id)
                ->assertPathIs('/documents/'.$createdDocument->id)
                ->assertPresent('#physical_location_id')
                ->select('physical_location_id', (string) $location->id)
                ->type('archive_note', 'Smoke full UI: archivado físico del documento creado');

            $browser->script(
                'document.querySelector(\'form[action="'.route('documents.archive-location.update', $createdDocument).'"]\').submit();'
            );

            $browser->waitForLocation('/documents/'.$createdDocument->id);

            $archiveDocumentSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($documentTitle, $archiveDocumentSource);
            $this->assertStringContainsString($location->full_path, $archiveDocumentSource);
            $this->assertStringContainsString('Archivo físico', $archiveDocumentSource);
        });

        $this->browse(function (Browser $browser) use ($regularUser, $receipt, $createdDocument, $documentTitle) {
            $this->loginThroughPortal($browser, $regularUser->email)
                ->visit('/receipts/'.$receipt->id)
                ->assertPathIs('/receipts/'.$receipt->id);

            $receiptSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($receipt->receipt_number, $receiptSource);
            $this->assertStringContainsString($regularUser->email, $receiptSource);

            $browser
                ->visit('/documents/'.$createdDocument->id)
                ->assertPathIs('/documents/'.$createdDocument->id);

            $documentDetailSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($documentTitle, $documentDetailSource);
            $this->assertStringContainsString(route('documents.preview', $createdDocument), $documentDetailSource);
            $this->assertStringNotContainsString('Sin archivo adjunto', $documentDetailSource);
        });

        $createdDocument->refresh();
        $distributionTarget->refresh();

        $this->assertSame('received', $distributionTarget->status);
        $this->assertNotNull($distributionTarget->received_at);
        $this->assertTrue($createdDocument->is_archived);
        $this->assertSame($location->id, $createdDocument->physical_location_id);
        $this->assertNotNull($createdDocument->file_path);
    }

    public function test_full_operational_portal_smoke_flow_closes_across_roles(): void
    {
        $receptionist = User::query()->where('email', 'qa.reception@archivemaster.test')->firstOrFail();
        $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->firstOrFail();
        $archiveManager = User::query()->where('email', 'qa.archive@archivemaster.test')->firstOrFail();
        $regularUser = User::query()->where('email', 'qa.user@archivemaster.test')->firstOrFail();

        $receiptDocument = Document::query()->where('document_number', 'QA-REC-0001')->firstOrFail();
        $approvalDocument = Document::query()->where('document_number', 'QA-APR-0001')->firstOrFail();
        $archiveDocument = Document::query()->where('document_number', 'QA-OFF-0001')->firstOrFail();
        $receipt = Receipt::query()->where('receipt_number', 'REC-QA-0001')->firstOrFail();
        $approval = DocumentApproval::query()
            ->where('document_id', $approvalDocument->id)
            ->where('approver_id', $officeManager->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $location = PhysicalLocation::query()
            ->where('company_id', $archiveManager->company_id)
            ->orderBy('id')
            ->firstOrFail();

        $this->browse(function (Browser $browser) use ($receptionist, $receiptDocument, $receipt) {
            $this->loginThroughPortal($browser, $receptionist->email)
                ->visit('/documents/'.$receiptDocument->id)
                ->assertPathIs('/documents/'.$receiptDocument->id)
                ->storeSource('smoke-receptionist-document')
                ->screenshot('smoke-receptionist-document')
                ->assertSee('QA-REC-0001')
                ->visit('/receipts/'.$receipt->id)
                ->assertPathIs('/receipts/'.$receipt->id);

            $receiptPageSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($receipt->receipt_number, $receiptPageSource);
            $this->assertStringContainsString($receptionist->name, $receiptPageSource);
        });

        $this->browse(function (Browser $browser) use ($officeManager, $approvalDocument, $approval) {
            $this->loginThroughPortal($browser, $officeManager->email)
                ->visit('/approvals')
                ->assertPathIs('/approvals');

            $approvalsIndexSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('Aprobaciones Pendientes', $approvalsIndexSource);
            $this->assertStringContainsString('QA-APR-0001', $approvalsIndexSource);

            $browser
                ->visit('/approvals/document/'.$approvalDocument->id)
                ->assertPathIs('/approvals/document/'.$approvalDocument->id);

            $approvalDetailSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('Revisar Documento para Aprobación', $approvalDetailSource);
            $this->assertStringContainsString('QA-APR-0001', $approvalDetailSource);

            $browser->script(
                'document.querySelector(\'form[action="'.route('approvals.approve', $approval).'"]\').submit();'
            );

            $browser->waitForLocation('/approvals');

            $approvalsAfterApproveSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('Documento aprobado correctamente', $approvalsAfterApproveSource);
            $this->assertStringNotContainsString('QA-APR-0001', $approvalsAfterApproveSource);
        });

        $this->browse(function (Browser $browser) use ($archiveManager, $archiveDocument, $location) {
            $this->loginThroughPortal($browser, $archiveManager->email)
                ->visit('/documents/'.$archiveDocument->id)
                ->assertPathIs('/documents/'.$archiveDocument->id)
                ->assertPresent('#physical_location_id')
                ->select('physical_location_id', (string) $location->id)
                ->type('archive_note', 'Smoke completo QA: archivado físico');

            $browser->script(
                'document.querySelector(\'form[action="'.route('documents.archive-location.update', $archiveDocument).'"]\').submit();'
            );

            $browser->waitForLocation('/documents/'.$archiveDocument->id);

            $archiveDocumentSource = $browser->driver->getPageSource();

            $this->assertStringContainsString($location->full_path, $archiveDocumentSource);
            $this->assertStringContainsString('Archivo físico', $archiveDocumentSource);
        });

        $this->browse(function (Browser $browser) use ($regularUser, $receipt, $receiptDocument) {
            $this->loginThroughPortal($browser, $regularUser->email)
                ->visit('/receipts/'.$receipt->id)
                ->assertPathIs('/receipts/'.$receipt->id);

            $receiptPageSource = $browser->driver->getPageSource();

            $browser
                ->visit('/documents/'.$receiptDocument->id)
                ->assertPathIs('/documents/'.$receiptDocument->id);

            $documentDetailSource = $browser->driver->getPageSource();

            $this->assertStringContainsString('REC-QA-0001', $receiptPageSource);
            $this->assertStringContainsString($regularUser->email, $receiptPageSource);
            $this->assertStringContainsString('QA-REC-0001', $documentDetailSource);
            $this->assertStringContainsString('Sin archivo adjunto', $documentDetailSource);
        });

        $approvalDocument->refresh();
        $archiveDocument->refresh();
        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame($officeManager->id, $approval->approver_id);
        $this->assertTrue($archiveDocument->is_archived);
        $this->assertSame($location->id, $archiveDocument->physical_location_id);
        $this->assertNotNull($approvalDocument->status);
        $this->assertTrue((bool) $approval->responded_at);
    }

    private function loginThroughPortal(Browser $browser, string $email): Browser
    {
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/');
        $browser->script('window.localStorage.clear(); window.sessionStorage.clear();');

        return $browser->visit('/login')
            ->waitFor('#portal_email')
            ->type('#portal_email', $email)
            ->type('#portal_password', 'Laboral2026!')
            ->press('Ingresar con contraseña')
            ->waitForLocation('/portal');
    }

    private function uploadFixturePath(): string
    {
        return base_path('tests/Fixtures/qa-upload.png');
    }

    private function createAndDistributeDocumentViaPortal(
        Browser $browser,
        string $receptionistEmail,
        int $officeDepartmentId,
        string $documentTitle,
        string $documentDescription,
        string $routingNote,
        string $recipientName,
        string $recipientEmail,
        string $recipientPhone,
    ): int {
        $this->loginThroughPortal($browser, $receptionistEmail)
            ->visit('/documents/create')
            ->assertPathIs('/documents/create');

        $browser->script(<<<'JS'
            const input = document.getElementById('file');
            if (input) {
                input.classList.remove('sr-only');
                input.style.display = 'block';
                input.style.visibility = 'visible';
                input.style.position = 'static';
            }
        JS);

        $browser->attach('#file', $this->uploadFixturePath())
            ->waitForText('qa-upload.png')
            ->waitForText('Listo')
            ->type('input[name="bulk_items[0][title]"]', $documentTitle)
            ->press('Continuar')
            ->waitFor('#description')
            ->type('#description', $documentDescription);

        $browser->script(<<<'JS'
            const setFirstNonEmptyOption = (id) => {
                const element = document.getElementById(id);

                if (! element || element.value || element.options.length < 2) {
                    return;
                }

                element.value = element.options[1].value;
                element.dispatchEvent(new Event('change', { bubbles: true }));
            };

            setFirstNonEmptyOption('category_id');
            setFirstNonEmptyOption('status_id');
        JS);

        $browser->press('Continuar')
            ->waitFor('#priority')
            ->select('#priority', 'medium')
            ->type('#recipient_name', $recipientName)
            ->type('#recipient_email', $recipientEmail)
            ->type('#recipient_phone', $recipientPhone)
            ->press('Continuar')
            ->waitForText('Revisión')
            ->press('Crear Documento');

        $browser->waitUsing(10, 100, function () use ($browser): bool {
            $currentPath = parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH);

            return is_string($currentPath) && preg_match('#^/documents/\d+$#', $currentPath) === 1;
        });

        $currentPath = (string) parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH);

        preg_match('#^/documents/(\d+)$#', $currentPath, $matches);

        $createdDocumentId = (int) ($matches[1] ?? 0);

        $this->assertGreaterThan(0, $createdDocumentId);

        $browser->assertPathIs('/documents/'.$createdDocumentId)
            ->assertSee($documentTitle)
            ->type('#routing_note', $routingNote);

        $browser->script(
            'document.querySelector(\'input[name="department_ids[]"][value="'.$officeDepartmentId.'"]\').click();'
        );

        $browser->press('Enviar a oficinas')
            ->waitForLocation('/documents/'.$createdDocumentId);

        $documentSource = $browser->driver->getPageSource();

        $this->assertStringContainsString($documentTitle, $documentSource);
        $this->assertStringContainsString($routingNote, $documentSource);

        return $createdDocumentId;
    }

    public function test_archive_manager_can_assign_a_physical_location_from_seeded_dataset(): void
    {
        $archiveManager = User::query()->where('email', 'qa.archive@archivemaster.test')->firstOrFail();
        $document = Document::query()->where('document_number', 'QA-OFF-0001')->firstOrFail();
        $location = PhysicalLocation::query()
            ->where('company_id', $archiveManager->company_id)
            ->orderBy('id')
            ->firstOrFail();

        $this->browse(function (Browser $browser) use ($archiveManager, $document, $location) {
            $this->loginThroughPortal($browser, $archiveManager->email)
                ->visit('/documents/'.$document->id)
                ->assertPathIs('/documents/'.$document->id)
                ->storeSource('archive-manager-document-show')
                ->screenshot('archive-manager-document-show')
                ->assertPresent('#physical_location_id')
                ->assertPresent('#archive_note')
                ->select('physical_location_id', (string) $location->id)
                ->type('archive_note', 'Asignación QA de ubicación física');

            $browser->script(
                'document.querySelector(\'form[action="'.route('documents.archive-location.update', $document).'"]\').submit();'
            );

            $browser
                ->waitForLocation('/documents/'.$document->id)
                ->assertPresent('#physical_location_id')
                ->assertSee($location->full_path);
        });

        $document->refresh();

        $this->assertSame($location->id, $document->physical_location_id);
        $this->assertTrue($document->is_archived);
    }
}
