<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class DocumentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Document $document,
        public Status $oldStatus,
        public Status $newStatus,
        public User $changedBy
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        
        // Agregar email si el usuario tiene configurado recibir notificaciones por email
        if ($notifiable->email && $this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $documentTitle = $this->document->title;
        $oldStatusName = $this->getStatusName($this->oldStatus);
        $newStatusName = $this->getStatusName($this->newStatus);
        $changedByName = $this->changedBy->name;
        
        return (new MailMessage)
            ->subject("Estado de documento actualizado: {$documentTitle}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El estado del documento '{$documentTitle}' ha sido actualizado.")
            ->line("**Estado anterior:** {$oldStatusName}")
            ->line("**Estado actual:** {$newStatusName}")
            ->line("**Actualizado por:** {$changedByName}")
            ->line("**Fecha:** " . now()->format('d/m/Y H:i'))
            ->action('Ver Documento', url("/admin/documents/{$this->document->id}"))
            ->line('Gracias por usar nuestro sistema de gestión documental.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'document_status_changed',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'old_status_id' => $this->oldStatus->id,
            'old_status_name' => $this->getStatusName($this->oldStatus),
            'new_status_id' => $this->newStatus->id,
            'new_status_name' => $this->getStatusName($this->newStatus),
            'changed_by_id' => $this->changedBy->id,
            'changed_by_name' => $this->changedBy->name,
            'changed_at' => now()->toISOString(),
            'url' => "/admin/documents/{$this->document->id}",
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Determinar si se debe enviar email
     */
    private function shouldSendEmail(object $notifiable): bool
    {
        // Aquí se puede implementar lógica más compleja basada en preferencias del usuario
        // Por ahora, enviar email solo para cambios importantes
        return in_array($this->newStatus->name, ['Approved', 'Rejected', 'Completed']);
    }

    /**
     * Obtener el nombre del estado considerando traducciones
     */
    private function getStatusName(Status $status): string
    {
        $name = $status->name;
        
        if (is_array($name)) {
            return $status->getTranslation('name', app()->getLocale());
        }
        
        return $name;
    }
}