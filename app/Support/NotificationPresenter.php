<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationPresenter
{
    /**
     * @return array{
     *   id:string,
     *   type:string,
     *   title:string,
     *   message:string,
     *   action_url:string,
     *   icon:string,
     *   color:string,
     *   created_at:string,
     *   read_at:mixed,
     *   meta:array<string,mixed>
     * }
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        $base = [
            'id' => (string) $notification->id,
            'type' => (string) ($data['type'] ?? 'generic'),
            'title' => (string) ($data['title'] ?? 'Notificación'),
            'message' => (string) ($data['message'] ?? ''),
            'action_url' => (string) ($data['action_url'] ?? $data['url'] ?? route('notifications.index')),
            'icon' => (string) ($data['icon'] ?? 'bell'),
            'color' => (string) ($data['color'] ?? 'gray'),
            'created_at' => $notification->created_at->diffForHumans(),
            'read_at' => $notification->read_at,
            'meta' => [],
        ];

        return match ($base['type']) {
            'document_distributed_to_office' => self::presentDistributedToOffice($base, $data),
            'document_distribution_target_updated' => self::presentDistributionUpdated($base, $data),
            default => $base,
        };
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private static function presentDistributedToOffice(array $base, array $data): array
    {
        $department = (string) ($data['department_name'] ?? 'Tu oficina');
        $documentTitle = (string) ($data['document_title'] ?? 'Documento');
        $documentNumber = (string) ($data['document_number'] ?? '');
        $sender = (string) ($data['sender_name'] ?? 'Recepción');
        $routingNote = self::singleLine($data['routing_note'] ?? null);

        $message = "Se recibió un documento en {$department}: {$documentTitle}.";
        if ($routingNote !== null) {
            $message .= ' Nota: '.$routingNote;
        }

        $base['title'] = 'Documento recibido por oficina';
        $base['message'] = $message;
        $base['icon'] = 'document';
        $base['color'] = 'blue';
        $base['meta'] = array_filter([
            'Oficina' => $department,
            'Documento' => $documentNumber !== '' ? $documentNumber : null,
            'Enviado por' => $sender,
        ]);

        return $base;
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private static function presentDistributionUpdated(array $base, array $data): array
    {
        $department = (string) ($data['department_name'] ?? 'Oficina');
        $statusLabel = (string) ($data['status_label'] ?? 'Actualizado');
        $actor = (string) ($data['actor_name'] ?? 'Usuario');
        $documentTitle = (string) ($data['document_title'] ?? 'Documento');
        $responseComment = self::singleLine($data['response_note'] ?? null);
        $rejectedReason = self::singleLine($data['rejected_reason'] ?? null);
        $responseDocumentTitle = (string) ($data['response_document_title'] ?? '');

        $base['title'] = "Respuesta de {$department}: {$statusLabel}";
        $base['icon'] = match ((string) ($data['status'] ?? '')) {
            'responded' => 'document',
            'rejected' => 'warning',
            'received', 'in_review' => 'refresh',
            default => 'bell',
        };
        $base['color'] = match ((string) ($data['status'] ?? '')) {
            'responded' => 'green',
            'rejected' => 'red',
            'received', 'in_review' => 'yellow',
            default => 'gray',
        };

        $message = "{$actor} actualizó {$documentTitle} ({$department}) a estado {$statusLabel}.";
        if ($rejectedReason !== null) {
            $message .= ' Motivo: '.$rejectedReason;
        } elseif ($responseComment !== null) {
            $message .= ' Comentario: '.$responseComment;
        }

        $base['message'] = $message;
        $base['meta'] = array_filter([
            'Oficina' => $department,
            'Estado' => $statusLabel,
            'Actor' => $actor,
            'Respuesta' => $responseDocumentTitle !== '' ? $responseDocumentTitle : null,
        ]);

        return $base;
    }

    private static function singleLine(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $plain = trim(strip_tags($value));

        if ($plain === '') {
            return null;
        }

        return Str::limit(preg_replace('/\s+/', ' ', $plain) ?: $plain, 140);
    }
}
