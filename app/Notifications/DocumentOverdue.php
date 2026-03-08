<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Document $document,
        public int $hoursOverdue
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get days overdue for backward compatibility
     */
    public function getDaysOverdue(): int
    {
        return (int) ceil($this->hoursOverdue / 24);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Enviar email solo si han pasado más de 72 horas (3 días)
        if ($this->hoursOverdue >= 72) {
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
        $dueDate = $this->document->sla_due_date ?? $this->document->due_date;

        if ($urgencyLevel === 'critical') {
            $subject = '🚨 URGENTE: '.$subject;
        }

        $daysOverdue = $this->getDaysOverdue();
        $timeOverdueText = $this->hoursOverdue < 24
            ? "{$this->hoursOverdue} horas"
            : "{$daysOverdue} días";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line("El documento **{$this->document->document_number}** está vencido hace **{$timeOverdueText}**.")
            ->line("**Título:** {$this->document->title}")
            ->line("**Estado actual:** {$this->getStatusName()}")
            ->line("**Categoría:** {$this->getCategoryName()}")
            ->line('**Fecha límite:** '.($dueDate?->format('d/m/Y H:i') ?? 'Sin fecha'))
            ->when(($this->document->priority?->value ?? $this->document->priority) === 'high', function ($message) {
                return $message->line('⚠️ **Este documento tiene prioridad ALTA**');
            })
            ->action('Ver Documento', route('documents.show', $this->document->id))
            ->line('Por favor, toma las acciones necesarias lo antes posible.')
            ->salutation('Saludos,\nSistema ArchiveMaster');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $daysOverdue = $this->getDaysOverdue();
        $timeOverdueText = $this->hoursOverdue < 24
            ? "{$this->hoursOverdue} horas"
            : "{$daysOverdue} días";
        $dueDate = $this->document->sla_due_date ?? $this->document->due_date;

        return [
            'type' => 'document_overdue',
            'document_id' => $this->document->id,
            'document_number' => $this->document->document_number,
            'document_title' => $this->document->title,
            'hours_overdue' => $this->hoursOverdue,
            'days_overdue' => $daysOverdue,
            'due_date' => $dueDate?->toISOString(),
            'status' => $this->getStatusName(),
            'category' => $this->getCategoryName(),
            'priority' => $this->document->priority?->value ?? $this->document->priority,
            'urgency_level' => $this->getUrgencyLevel(),
            'action_url' => route('documents.show', $this->document->id),
            'message' => "El documento {$this->document->document_number} está vencido hace {$timeOverdueText}",
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
     * Obtener el nivel de urgencia basado en horas vencidas
     */
    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->hoursOverdue >= 360 => 'critical', // 15 días
            $this->hoursOverdue >= 168 => 'high',     // 7 días
            $this->hoursOverdue >= 72 => 'medium',    // 3 días
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
        if (! $this->document->category) {
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
        if (! $notifiable->can('view', $this->document)) {
            return false;
        }

        return true;
    }

    /**
     * Get the notification's unique identifier
     */
    public function uniqueId(): string
    {
        return "document_overdue_{$this->document->id}_{$this->getDaysOverdue()}";
    }

    /**
     * Determine the time at which the notification should be sent.
     */
    public function delay(object $notifiable): ?\DateTime
    {
        // Enviar inmediatamente para documentos críticos
        if ($this->getUrgencyLevel() === 'critical') {
            return null;
        }

        // Retrasar 1 hora para otros casos para evitar spam
        return now()->addHour();
    }
}
