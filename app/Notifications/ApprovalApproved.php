<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;

    protected $approver;

    protected $level;

    public function __construct(Document $document, User $approver, int $level)
    {
        $this->document = $document;
        $this->approver = $approver;
        $this->level = $level;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_approved',
            'title' => 'Aprobación completada',
            'message' => $this->approver->name.' aprobó: '.$this->document->title,
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'approved_by' => $this->approver->name,
            'approval_level' => $this->level,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'check',
            'color' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aprobación completada - Nivel '.$this->level)
            ->line('Tu documento ha sido aprobado.')
            ->line('Documento: '.$this->document->title)
            ->line('Aprobado por: '.$this->approver->name)
            ->line('Nivel: '.$this->level)
            ->action('Ver Documento', route('documents.show', $this->document->id));
    }
}
