<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;
    protected $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document, int $daysRemaining)
    {
        $this->document = $document;
        $this->daysRemaining = $daysRemaining;
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
        $urgencyLevel = $this->daysRemaining <= 1 ? 'urgent' : ($this->daysRemaining <= 3 ? 'warning' : 'info');

        return [
            'type' => 'document_due_soon',
            'title' => 'Documento próximo a vencer',
            'message' => $this->daysRemaining === 0
                ? 'El documento "' . $this->document->title . '" vence hoy'
                : 'El documento "' . $this->document->title . '" vence en ' . $this->daysRemaining . ' día(s)',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'days_remaining' => $this->daysRemaining,
            'due_date' => $this->document->due_at?->format('d/m/Y'),
            'urgency_level' => $urgencyLevel,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'clock',
            'color' => $this->daysRemaining <= 1 ? 'red' : 'orange',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->daysRemaining === 0
            ? 'Documento vence hoy'
            : 'Documento próximo a vencer';

        return (new MailMessage)
            ->subject($subject)
            ->line($this->daysRemaining === 0
                ? 'Un documento asignado a ti vence hoy.'
                : 'Un documento asignado a ti vencerá pronto.')
            ->line('Documento: ' . $this->document->title)
            ->line('Días restantes: ' . $this->daysRemaining)
            ->action('Ver Documento', route('documents.show', $this->document->id))
            ->line('Por favor, toma las acciones necesarias.');
    }
}
