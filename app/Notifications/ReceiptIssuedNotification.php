<?php

namespace App\Notifications;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Receipt $receipt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->receipt->document;
        $companyName = $this->receipt->company?->name ?? config('app.name');

        return (new MailMessage)
            ->subject('Confirmación de recibido '.$this->receipt->receipt_number)
            ->greeting('Hola '.$this->receipt->recipient_name.',')
            ->line('La recepción de '.$companyName.' registró correctamente tu documento.')
            ->line('Entidad receptora: '.$companyName)
            ->line('Radicado de recibido: '.$this->receipt->receipt_number)
            ->line('Número de documento: '.($document?->document_number ?: 'Pendiente de asignación'))
            ->line('Documento: '.($document?->title ?: 'Documento radicado'))
            ->line('Radicado por: '.$this->receipt->recipient_name)
            ->line('Puedes conservar este correo como comprobante de radicación.')
            ->action('Ingresar al portal', route('login'))
            ->line('Para consultar el estado del documento usa tu número de recibido y este correo electrónico en el portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
