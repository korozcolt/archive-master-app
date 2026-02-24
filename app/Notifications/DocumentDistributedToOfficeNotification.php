<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentDistributionTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDistributedToOfficeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Document $document,
        public DocumentDistributionTarget $target,
        public string $senderName
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $departmentName = $this->translatedName($this->target->department?->name, 'su oficina');
        $note = $this->target->routing_note ?: $this->target->distribution?->notes;

        $message = (new MailMessage)
            ->subject("Documento enviado a {$departmentName}: {$this->document->title}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se ha enviado un documento a {$departmentName} para revisión/gestión.")
            ->line("Documento: {$this->document->title}")
            ->line("Número: {$this->document->document_number}")
            ->line("Enviado por: {$this->senderName}");

        if ($note) {
            $message->line("Nota de envío: {$note}");
        }

        return $message
            ->action('Abrir documento', route('documents.show', $this->document))
            ->line('Puedes registrar recepción, revisión, rechazo o respuesta desde el portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_distributed_to_office',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'department_id' => $this->target->department_id,
            'department_name' => $this->translatedName($this->target->department?->name),
            'target_id' => $this->target->id,
            'status' => $this->target->status,
            'routing_note' => $this->target->routing_note,
            'sent_at' => $this->target->sent_at?->toISOString(),
            'sender_name' => $this->senderName,
            'url' => route('documents.show', $this->document),
        ];
    }

    private function translatedName(mixed $value, string $fallback = 'N/D'): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? $value['es'] ?? $value['en'] ?? reset($value) ?: $fallback);
        }

        if (is_string($value) && str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?: $fallback);
            }
        }

        return (string) ($value ?: $fallback);
    }
}
