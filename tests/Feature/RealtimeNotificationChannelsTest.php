<?php

use App\Models\Document;
use App\Models\DocumentDistributionTarget;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\ApprovalApproved;
use App\Notifications\ApprovalRejected;
use App\Notifications\ApprovalRequested;
use App\Notifications\DocumentAssigned;
use App\Notifications\DocumentDistributedToOfficeNotification;
use App\Notifications\DocumentDistributionTargetUpdatedNotification;
use App\Notifications\DocumentDueSoon;
use App\Notifications\DocumentOverdue;
use App\Notifications\DocumentStatusChanged;
use App\Notifications\DocumentUpdate;

it('ensures all in-app notifications include broadcast channel for realtime delivery', function () {
    $document = $this->createMock(Document::class);
    $user = $this->createMock(User::class);
    $status = $this->createMock(Status::class);
    $workflowDefinition = $this->createMock(WorkflowDefinition::class);
    $distributionTarget = $this->createMock(DocumentDistributionTarget::class);

    $notifiable = (object) [
        'email' => null,
        'name' => 'Realtime User',
    ];

    $notifications = [
        new ApprovalApproved($document, $user, 1),
        new ApprovalRejected($document, $user, 1, 'Motivo de prueba'),
        new ApprovalRequested($document, $workflowDefinition, 1),
        new DocumentAssigned($document, $user),
        new DocumentDistributedToOfficeNotification($document, $distributionTarget, 'Recepcionista'),
        new DocumentDistributionTargetUpdatedNotification($document, $distributionTarget, 'Archivo'),
        new DocumentDueSoon($document, 2),
        new DocumentOverdue($document, 12),
        new DocumentStatusChanged($document, $status, $status, $user),
        new DocumentUpdate($document, $user),
    ];

    foreach ($notifications as $notification) {
        expect($notification->via($notifiable))
            ->toContain('database')
            ->toContain('broadcast');
    }
});
