<?php

namespace App\Notifications;

use App\Models\DocumentAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentAccessRequestResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DocumentAccessRequest $accessRequest
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->accessRequest->document;
        $approved = $this->accessRequest->status === 'approved';

        $message = (new MailMessage)
            ->subject(($approved ? 'Acceso aprobado' : 'Acceso rechazado').": {$document->title}")
            ->greeting("Hola {$notifiable->name},")
            ->line($approved
                ? "Tu solicitud de acceso al documento \"{$document->title}\" fue aprobada."
                : "Tu solicitud de acceso al documento \"{$document->title}\" fue rechazada.");

        if ($this->accessRequest->resolution_note) {
            $message->line("Nota: {$this->accessRequest->resolution_note}");
        }

        if ($approved && $this->accessRequest->expires_at) {
            $message->line("Tu acceso expira el {$this->accessRequest->expires_at->format('d/m/Y H:i')}.");
        }

        return $message->action('Ver documento', route('documents.show', $document));
    }

    public function toArray(object $notifiable): array
    {
        $document = $this->accessRequest->document;

        return [
            'type' => 'document_access_request_resolved',
            'access_request_id' => $this->accessRequest->id,
            'document_id' => $document->id,
            'document_title' => $document->title,
            'status' => $this->accessRequest->status,
            'resolution_note' => $this->accessRequest->resolution_note,
            'expires_at' => $this->accessRequest->expires_at?->toISOString(),
            'url' => route('documents.show', $document),
        ];
    }
}
