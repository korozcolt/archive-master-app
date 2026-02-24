<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentDistributionTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDistributionTargetUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Document $document,
        public DocumentDistributionTarget $target,
        public string $actorName
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
        $departmentName = $this->translatedName($this->target->department?->name, 'una oficina');
        $statusLabel = $this->statusLabel($this->target->status);
        $message = (new MailMessage)
            ->subject("Actualización de respuesta de oficina: {$this->document->title}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El documento radicado '{$this->document->title}' tuvo una actualización por parte de {$departmentName}.")
            ->line("Estado de la oficina: {$statusLabel}")
            ->line("Actualizado por: {$this->actorName}");

        if ($this->target->status === 'rejected' && $this->target->rejected_reason) {
            $message->line("Motivo de rechazo: {$this->target->rejected_reason}");
        }

        if ($this->target->response_note) {
            $message->line("Comentario / respuesta: {$this->target->response_note}");
        }

        if ($this->target->responseDocument) {
            $message->line('Documento de respuesta oficial: '.$this->target->responseDocument->title)
                ->line('Número de documento de respuesta: '.($this->target->responseDocument->document_number ?? 'N/D'));
        }

        return $message
            ->action('Ver documento', route('documents.show', $this->document))
            ->line('Revisa el panel de distribución y seguimiento para ver el detalle por oficina.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_distribution_target_updated',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'department_id' => $this->target->department_id,
            'department_name' => $this->translatedName($this->target->department?->name),
            'target_id' => $this->target->id,
            'status' => $this->target->status,
            'status_label' => $this->statusLabel($this->target->status),
            'actor_name' => $this->actorName,
            'response_note' => $this->target->response_note,
            'rejected_reason' => $this->target->rejected_reason,
            'response_type' => $this->target->response_type,
            'response_document_id' => $this->target->response_document_id,
            'response_document_title' => $this->target->responseDocument?->title,
            'updated_at' => now()->toISOString(),
            'url' => route('documents.show', $this->document),
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'sent' => 'Enviado',
            'received' => 'Recibido',
            'in_review' => 'En revisión',
            'responded' => 'Respondido',
            'rejected' => 'Rechazado',
            'closed' => 'Cerrado',
            default => (string) ($status ?: 'Sin estado'),
        };
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
