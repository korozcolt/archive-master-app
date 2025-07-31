<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class DocumentOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Document $document,
        public int $daysOverdue
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        
        // Enviar email solo si han pasado más de 3 días
        if ($this->daysOverdue >= 3) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $urgencyLevel = $this->getUrgencyLevel();
        $subject = "⚠️ Documento Vencido - {$this->document->document_number}";
        
        if ($urgencyLevel === 'critical') {
            $subject = "🚨 URGENTE: " . $subject;
        }
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line("El documento **{$this->document->document_number}** está vencido hace **{$this->daysOverdue} días**.")
            ->line("**Título:** {$this->document->title}")
            ->line("**Estado actual:** {$this->getStatusName()}")
            ->line("**Categoría:** {$this->getCategoryName()}")
            ->line("**Fecha límite:** {$this->document->due_at->format('d/m/Y H:i')}")
            ->when($this->document->priority === 'high', function ($message) {
                return $message->line("⚠️ **Este documento tiene prioridad ALTA**");
            })
            ->action('Ver Documento', url("/admin/documents/{$this->document->id}"))
            ->line('Por favor, toma las acciones necesarias lo antes posible.')
            ->salutation('Saludos,\nSistema ArchiveMaster');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'document_overdue',
            'document_id' => $this->document->id,
            'document_number' => $this->document->document_number,
            'document_title' => $this->document->title,
            'days_overdue' => $this->daysOverdue,
            'due_date' => $this->document->due_at->toISOString(),
            'status' => $this->getStatusName(),
            'category' => $this->getCategoryName(),
            'priority' => $this->document->priority,
            'urgency_level' => $this->getUrgencyLevel(),
            'action_url' => "/admin/documents/{$this->document->id}",
            'message' => "El documento {$this->document->document_number} está vencido hace {$this->daysOverdue} días",
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
     * Obtener el nivel de urgencia basado en días vencidos
     */
    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->daysOverdue >= 15 => 'critical',
            $this->daysOverdue >= 7 => 'high',
            $this->daysOverdue >= 3 => 'medium',
            default => 'low',
        };
    }

    /**
     * Obtener el nombre del estado
     */
    private function getStatusName(): string
    {
        $statusName = $this->document->status->name;
        
        if (is_array($statusName)) {
            return $this->document->status->getTranslation('name', app()->getLocale());
        }
        
        return $statusName;
    }

    /**
     * Obtener el nombre de la categoría
     */
    private function getCategoryName(): string
    {
        if (!$this->document->category) {
            return 'Sin categoría';
        }
        
        $categoryName = $this->document->category->name;
        
        if (is_array($categoryName)) {
            return $this->document->category->getTranslation('name', app()->getLocale());
        }
        
        return $categoryName;
    }

    /**
     * Determinar si la notificación debe ser enviada
     */
    public function shouldSend(object $notifiable): bool
    {
        // No enviar si el documento ya está completado
        if ($this->document->status->is_final) {
            return false;
        }
        
        // No enviar si el usuario no tiene permisos para ver el documento
        if (!$notifiable->can('view', $this->document)) {
            return false;
        }
        
        return true;
    }

    /**
     * Get the notification's unique identifier
     */
    public function uniqueId(): string
    {
        return "document_overdue_{$this->document->id}_{$this->daysOverdue}";
    }

    /**
     * Determine the time at which the notification should be sent.
     */
    public function delay(object $notifiable): \DateTime|null
    {
        // Enviar inmediatamente para documentos críticos
        if ($this->getUrgencyLevel() === 'critical') {
            return null;
        }
        
        // Retrasar 1 hora para otros casos para evitar spam
        return now()->addHour();
    }
}