<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;
    protected $assignedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document, $assignedBy = null)
    {
        $this->document = $document;
        $this->assignedBy = $assignedBy;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_assigned',
            'title' => 'Documento asignado',
            'message' => 'Se te ha asignado el documento: ' . $this->document->title,
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'assigned_by' => $this->assignedBy ? $this->assignedBy->name : 'Sistema',
            'priority' => $this->document->priority->value,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'document',
            'color' => 'blue',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo documento asignado')
            ->line('Se te ha asignado un nuevo documento.')
            ->line('Documento: ' . $this->document->title)
            ->line('Prioridad: ' . $this->document->priority->getLabel())
            ->action('Ver Documento', route('documents.show', $this->document->id))
            ->line('Gracias por usar ' . config('app.name'));
    }
}
