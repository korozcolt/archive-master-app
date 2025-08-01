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

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Document $document,
        public User $updatedBy,
        public array $changes = [],
        public ?string $comment = null
    ) {
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
        $subject = 'Document Updated: ' . $this->document->title;
        
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name)
            ->line('The document "' . $this->document->title . '" has been updated by ' . $this->updatedBy->name . '.')
            ->line('Document Number: ' . $this->document->document_number)
            ->line('Company: ' . ($this->document->company->name ?? 'N/A'));

        // Add changes information
        if (!empty($this->changes)) {
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
            $message->line('Comment: ' . $this->comment);
        }

        // Add action button
        $viewUrl = URL::signedRoute('documents.show', ['document' => $this->document->id]);
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
            'document_id' => $this->document->id,
            'document_number' => $this->document->document_number,
            'document_title' => $this->document->title,
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'changes' => $this->changes,
            'comment' => $this->comment,
            'company_id' => $this->document->company_id,
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get a unique identifier for the notification.
     */
    public function uniqueId(): string
    {
        return 'document_update_' . $this->document->id . '_' . now()->timestamp;
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
            'document_id' => $this->document->id,
            'updated_by_id' => $this->updatedBy->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
