<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DocumentArchiveClassificationMissing extends Notification implements ShouldQueue
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
            'type' => 'document_archive_classification_missing',
            'title' => 'Archivo incompleto',
            'message' => 'El documento archivado "'.$this->document->title.'" aún no tiene clasificación archivística completa.',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_number' => $this->document->document_number,
            'action_url' => route('documents.show', $this->document->id),
            'icon' => 'exclamation-triangle',
            'color' => 'amber',
        ];
    }
}
