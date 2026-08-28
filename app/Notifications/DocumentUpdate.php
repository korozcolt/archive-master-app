<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class DocumentUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 60;

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
     * Create a new notification instance.
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
            'company_name' => $document->company?->name ?? 'N/A',
            'document_number' => $document->document_number,
            'title' => $document->title,
        ];
        $this->updatedBySnapshot = [
            'name' => $updatedBy->name,
        ];
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Document Updated: '.$this->documentSnapshot['title'];

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name)
            ->line('The document "'.$this->documentSnapshot['title'].'" has been updated by '.$this->updatedBySnapshot['name'].'.')
            ->line('Document Number: '.$this->documentSnapshot['document_number'])
            ->line('Company: '.$this->documentSnapshot['company_name']);

        // Add changes information
        if (! empty($this->changes)) {
            $message->line('Changes made:');
            foreach ($this->changes as $field => $change) {
                if (is_array($change) && isset($change['old'], $change['new'])) {
                    $fieldName = $this->getFieldDisplayName($field);
                    $message->line("• {$fieldName}: {$change['old']} → {$change['new']}");
                }
            }
        }

        // Add comment if provided
        if ($this->comment) {
            $message->line('Comment: '.$this->comment);
        }

        // Add action button
        $viewUrl = URL::signedRoute('documents.show', ['document' => $this->documentId]);
        $message->action('View Document', $viewUrl);

        $message->line('Thank you for using our document management system!');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->documentId,
            'document_number' => $this->documentSnapshot['document_number'],
            'document_title' => $this->documentSnapshot['title'],
            'updated_by_id' => $this->updatedById,
            'updated_by_name' => $this->updatedBySnapshot['name'],
            'changes' => $this->changes,
            'comment' => $this->comment,
            'company_id' => $this->documentSnapshot['company_id'],
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get a unique identifier for the notification.
     */
    public function uniqueId(): string
    {
        return 'document_update_'.$this->documentId.'_'.now()->timestamp;
    }

    /**
     * Get display name for field.
     */
    private function getFieldDisplayName(string $field): string
    {
        $fieldNames = [
            'title' => 'Title',
            'description' => 'Description',
            'status' => 'Status',
            'assigned_to' => 'Assigned To',
            'due_date' => 'Due Date',
            'priority' => 'Priority',
            'category_id' => 'Category',
            'department_id' => 'Department',
            'tags' => 'Tags',
        ];

        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DocumentUpdate notification failed', [
            'document_id' => $this->documentId,
            'updated_by_id' => $this->updatedById,
            'exception' => $exception->getMessage(),
        ]);
    }
}
