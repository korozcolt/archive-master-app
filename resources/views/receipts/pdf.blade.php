<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .header { margin-bottom: 24px; border-bottom: 1px solid #d1d5db; padding-bottom: 12px; }
        .title { font-size: 20px; font-weight: bold; margin: 0; }
        .subtitle { margin: 4px 0 0; color: #6b7280; }
        .row { margin: 8px 0; }
        .label { font-weight: bold; width: 180px; display: inline-block; }
        .footer { margin-top: 24px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Recibido {{ $receipt->receipt_number }}</p>
        <p class="subtitle">Emitido: {{ $receipt->issued_at->format('d/m/Y H:i') }}</p>
    </div>

    <div class="row"><span class="label">Documento:</span> {{ $receipt->document->title }}</div>
    <div class="row"><span class="label">Número de documento:</span> {{ $receipt->document->document_number }}</div>
    <div class="row"><span class="label">Receptor:</span> {{ $receipt->recipient_name }}</div>
    <div class="row"><span class="label">Correo:</span> {{ $receipt->recipient_email }}</div>
    <div class="row"><span class="label">Teléfono:</span> {{ $receipt->recipient_phone ?: 'N/A' }}</div>
    <div class="row"><span class="label">Emitido por:</span> {{ $receipt->issuer->name }}</div>

    <div class="footer">
        Este recibido acredita la radicación y habilita el acceso autenticado al portal.
    </div>
</body>
</html>

