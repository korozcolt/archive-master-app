<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;
    protected $workflow;
    protected $level;

    public function __construct(Document $document, Workflow $workflow, int $level)
    {
        $this->document = $document;
        $this->workflow = $workflow;
        $this->level = $level;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_requested',
            'title' => 'Aprobación requerida',
            'message' => 'Se requiere tu aprobación para: ' . $this->document->title,
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'workflow_name' => $this->workflow->name,
            'approval_level' => $this->level,
            'priority' => $this->document->priority->value ?? 'medium',
            'action_url' => route('approvals.show', $this->document->id),
            'icon' => 'document',
            'color' => 'blue',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aprobación requerida - Nivel ' . $this->level)
            ->line('Se requiere tu aprobación para el siguiente documento:')
            ->line('Documento: ' . $this->document->title)
            ->line('Workflow: ' . $this->workflow->name)
            ->line('Nivel de aprobación: ' . $this->level)
            ->action('Revisar y Aprobar', route('approvals.show', $this->document->id))
            ->line('Por favor, revisa el documento a la brevedad.');
    }
}
