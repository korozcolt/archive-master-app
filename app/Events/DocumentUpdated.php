<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $documentId;

    public int $updatedById;

    /**
     * @var array<string, mixed>
     */
    public array $documentSnapshot;

    /**
     * @var array<string, mixed>
     */
    public array $updatedBySnapshot;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Document $document,
        User $updatedBy,
        public array $changes = [],
        public ?string $comment = null,
    ) {
        $this->documentId = $document->id;
        $this->updatedById = $updatedBy->id;
        $this->documentSnapshot = [
            'company_id' => $document->company_id,
            'document_number' => $document->document_number,
            'title' => $document->title,
        ];
        $this->updatedBySnapshot = [
            'name' => $updatedBy->name,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('document.'.$this->documentId),
            new PrivateChannel('company.'.$this->documentSnapshot['company_id']),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'document_number' => $this->documentSnapshot['document_number'],
            'document_title' => $this->documentSnapshot['title'],
            'updated_by' => [
                'id' => $this->updatedById,
                'name' => $this->updatedBySnapshot['name'],
            ],
            'changes' => $this->changes,
            'comment' => $this->comment,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.updated';
    }
}
