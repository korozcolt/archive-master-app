<?php

namespace App\Mail;

use App\Models\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public ScheduledReport $scheduledReport;
    public string $filePath;

    /**
     * Create a new message instance.
     */
    public function __construct(ScheduledReport $scheduledReport, string $filePath)
    {
        $this->scheduledReport = $scheduledReport;
        $this->filePath = $filePath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte Programado: ' . $this->scheduledReport->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
            with: [
                'reportName' => $this->scheduledReport->name,
                'reportDescription' => $this->scheduledReport->description,
                'frequency' => $this->getFrequencyLabel(),
                'generatedAt' => Carbon::now()->format('d/m/Y H:i'),
                'userName' => $this->scheduledReport->user->name,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (!Storage::exists($this->filePath)) {
            return [];
        }

        $extension = pathinfo($this->filePath, PATHINFO_EXTENSION);
        $fileName = $this->scheduledReport->name . '_' . Carbon::now()->format('Y-m-d') . '.' . $extension;

        return [
            Attachment::fromStorage($this->filePath)
                ->as($fileName)
                ->withMime($this->getMimeType($extension))
        ];
    }

    /**
     * Get frequency label in Spanish
     */
    private function getFrequencyLabel(): string
    {
        return match ($this->scheduledReport->schedule_frequency) {
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            default => 'Personalizado'
        };
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            default => 'application/octet-stream'
        };
    }
}
