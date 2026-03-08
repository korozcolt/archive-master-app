<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DocumentReadyForArchive extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Document $document)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_ready_for_archive',
            'title' => 'Documento listo para archivar',
            'message' => 'El documento "'.$this->document->title.'" ya fue cerrado y requiere clasificación/ubicación de archivo.',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'archive-box',
            'color' => 'sky',
        ];
    }
}
