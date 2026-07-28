<?php

namespace App\Notifications;

use App\Models\DocumentAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentAccessRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DocumentAccessRequest $accessRequest,
        public string $requesterName
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

        $message = (new MailMessage)
            ->subject("Solicitud de acceso a documento: {$document->title}")
            ->greeting("Hola {$notifiable->name},")
            ->line("{$this->requesterName} solicitó acceso al documento \"{$document->title}\".")
            ->line("Número: {$document->document_number}");

        if ($this->accessRequest->reason) {
            $message->line("Motivo: {$this->accessRequest->reason}");
        }

        return $message
            ->action('Revisar solicitud', route('access-requests.index'))
            ->line('Puedes aprobar o rechazar la solicitud desde el portal.');
    }

    public function toArray(object $notifiable): array
    {
        $document = $this->accessRequest->document;

        return [
            'type' => 'document_access_requested',
            'access_request_id' => $this->accessRequest->id,
            'document_id' => $document->id,
            'document_title' => $document->title,
            'document_number' => $document->document_number,
            'requester_name' => $this->requesterName,
            'reason' => $this->accessRequest->reason,
            'requested_at' => $this->accessRequest->requested_at?->toISOString(),
            'url' => route('access-requests.index'),
        ];
    }
}
