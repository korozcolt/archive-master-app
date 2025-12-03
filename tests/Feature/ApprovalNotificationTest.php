<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\WorkflowDefinition;
use App\Models\DocumentApproval;
use App\Models\Status;
use App\Services\WorkflowService;
use App\Notifications\ApprovalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->approver = User::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->fromStatus = Status::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Draft',
    ]);
    $this->toStatus = Status::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Pending Approval',
    ]);

    $this->document = Document::factory()->create([
        'company_id' => $this->company->id,
        'status_id' => $this->fromStatus->id,
        'created_by' => $this->user->id,
    ]);

    $this->workflowDefinition = WorkflowDefinition::factory()->create([
        'company_id' => $this->company->id,
        'from_status_id' => $this->fromStatus->id,
        'to_status_id' => $this->toStatus->id,
        'requires_approval' => true,
    ]);
});

describe('Approval Notification System', function () {
    test('sends notification when approval is requested', function () {
        Notification::fake();

        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $result = $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        expect($result['success'])->toBeTrue()
            ->and($result['approvals'])->toHaveCount(1);

        Notification::assertSentTo(
            $this->approver,
            ApprovalRequested::class,
            function ($notification, $channels) {
                $array = $notification->toArray($this->approver);
                return $array['document_id'] === $this->document->id
                    && $array['workflow_name'] === $this->workflowDefinition->name;
            }
        );
    });

    test('sends notification to multiple approvers', function () {
        Notification::fake();

        $approver2 = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $approver3 = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id, $approver2->id, $approver3->id]
        );

        Notification::assertSentTo(
            [$this->approver, $approver2, $approver3],
            ApprovalRequested::class
        );
    });

    test('notification contains correct document information', function () {
        Notification::fake();

        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        Notification::assertSentTo(
            $this->approver,
            ApprovalRequested::class,
            function ($notification) {
                $array = $notification->toArray($this->approver);

                return $array['type'] === 'approval_requested'
                    && $array['document_id'] === $this->document->id
                    && $array['document_title'] === $this->document->title
                    && $array['workflow_name'] === $this->workflowDefinition->name;
            }
        );
    });

    test('does not send notification if no approvers provided', function () {
        Notification::fake();

        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        try {
            $workflowService->createApprovals(
                $this->document,
                $this->workflowDefinition,
                []
            );
        } catch (\Exception $e) {
            // Expected to throw exception
        }

        Notification::assertNothingSent();
    });
});

describe('Approval Records Creation', function () {
    test('creates approval records in database', function () {
        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        $this->assertDatabaseHas('document_approvals', [
            'document_id' => $this->document->id,
            'workflow_definition_id' => $this->workflowDefinition->id,
            'approver_id' => $this->approver->id,
            'status' => 'pending',
        ]);
    });

    test('creates workflow history entry', function () {
        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        $this->assertDatabaseHas('workflow_histories', [
            'document_id' => $this->document->id,
            'from_status_id' => $this->fromStatus->id,
            'to_status_id' => $this->toStatus->id,
            'performed_by' => $this->user->id,
        ]);
    });

    test('creates multiple approvals for multiple approvers', function () {
        $approver2 = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $result = $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id, $approver2->id]
        );

        expect($result['approvals'])->toHaveCount(2);

        $this->assertDatabaseCount('document_approvals', 2);
    });
});

describe('Approval Workflow Integration', function () {
    test('approval belongs to correct document', function () {
        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        $approval = DocumentApproval::first();

        expect($approval->document->id)->toBe($this->document->id);
    });

    test('approval belongs to correct workflow definition', function () {
        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        $approval = DocumentApproval::first();

        expect($approval->workflowDefinition->id)->toBe($this->workflowDefinition->id);
    });

    test('approval assigned to correct approver', function () {
        $workflowService = app(WorkflowService::class);

        $this->actingAs($this->user);

        $workflowService->createApprovals(
            $this->document,
            $this->workflowDefinition,
            [$this->approver->id]
        );

        $approval = DocumentApproval::first();

        expect($approval->approver_id)->toBe($this->approver->id);
    });
});

describe('ApprovalRequested Notification', function () {
    test('notification uses database channel', function () {
        $notification = new ApprovalRequested(
            $this->document,
            $this->workflowDefinition
        );

        $channels = $notification->via($this->approver);

        expect($channels)->toContain('database');
    });

    test('notification array contains required fields', function () {
        $notification = new ApprovalRequested(
            $this->document,
            $this->workflowDefinition
        );

        $array = $notification->toArray($this->approver);

        expect($array)->toHaveKeys([
            'type',
            'title',
            'message',
            'document_id',
            'document_title',
            'document_number',
            'workflow_name',
            'approval_level',
            'priority',
            'action_url',
            'icon',
            'color',
        ]);
    });

    test('notification uses correct workflow definition name', function () {
        $notification = new ApprovalRequested(
            $this->document,
            $this->workflowDefinition
        );

        $array = $notification->toArray($this->approver);

        expect($array['workflow_name'])->toBe($this->workflowDefinition->name);
    });
});
