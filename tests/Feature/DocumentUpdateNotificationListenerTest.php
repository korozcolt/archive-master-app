<?php

use App\Events\DocumentUpdated;
use App\Listeners\SendDocumentUpdateNotification;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips stale document update notifications when the document was deleted before the queue runs', function (): void {
    $updatedBy = User::factory()->create();
    $document = Document::factory()->create([
        'company_id' => $updatedBy->company_id,
        'created_by' => $updatedBy->id,
        'assigned_to' => null,
    ]);

    $event = unserialize(serialize(new DocumentUpdated(
        document: $document,
        updatedBy: $updatedBy,
        changes: ['status' => ['old' => 'revision', 'new' => 'archivado']],
    )));

    $document->delete();

    app(SendDocumentUpdateNotification::class)->handle($event);

    expect(Document::query()->find($document->id))->toBeNull();
});

it('keeps document update notification data available after the document is deleted', function (): void {
    $updatedBy = User::factory()->create();
    $notifiable = User::factory()->create(['company_id' => $updatedBy->company_id]);
    $document = Document::factory()->create([
        'company_id' => $updatedBy->company_id,
        'created_by' => $updatedBy->id,
        'assigned_to' => $notifiable->id,
    ]);

    $notification = unserialize(serialize(new DocumentUpdate(
        document: $document,
        updatedBy: $updatedBy,
        changes: ['priority' => ['old' => 'medium', 'new' => 'high']],
    )));

    $document->delete();

    expect($notification->toArray($notifiable))
        ->toMatchArray([
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'document_title' => $document->title,
            'updated_by_id' => $updatedBy->id,
            'updated_by_name' => $updatedBy->name,
        ]);
});
