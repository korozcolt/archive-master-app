<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; line-height: 1.5; }
        .header { border-bottom: 2px solid #41a6b3; padding-bottom: 10px; margin-bottom: 20px; }
        .meta { color: #4b5563; font-size: 11px; margin-top: 8px; }
        h1 { font-size: 18px; margin: 0; color: #0f172a; }
        .content { white-space: pre-wrap; text-align: justify; }
        .footer { margin-top: 30px; border-top: 1px solid #d1d5db; padding-top: 10px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">
            <div><strong>Empresa:</strong> {{ $company }}</div>
            <div><strong>Autor:</strong> {{ $author }}</div>
            <div><strong>Fecha:</strong> {{ $date }}</div>
        </div>
    </div>

    <div class="content">{{ $content }}</div>

    <div class="footer">
        Documento de prueba generado automáticamente para QA / regresión visual.
    </div>
</body>
</html>
