<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;
    protected $approver;
    protected $level;
    protected $comments;

    public function __construct(Document $document, User $approver, int $level, string $comments)
    {
        $this->document = $document;
        $this->approver = $approver;
        $this->level = $level;
        $this->comments = $comments;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_rejected',
            'title' => 'Aprobación rechazada',
            'message' => $this->approver->name . ' rechazó: ' . $this->document->title,
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'rejected_by' => $this->approver->name,
            'approval_level' => $this->level,
            'comments' => $this->comments,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'x',
            'color' => 'red',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aprobación rechazada - Nivel ' . $this->level)
            ->line('Tu documento ha sido rechazado.')
            ->line('Documento: ' . $this->document->title)
            ->line('Rechazado por: ' . $this->approver->name)
            ->line('Motivo: ' . $this->comments)
            ->action('Ver Documento', route('documents.show', $this->document->id))
            ->line('Por favor, realiza las correcciones necesarias.');
    }
}
