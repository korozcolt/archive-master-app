<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Programado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        .info-value {
            color: #6b7280;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        .attachment-notice {
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .attachment-notice h3 {
            color: #1e40af;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .attachment-notice p {
            margin: 0;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Reporte Programado</h1>
    </div>

    <div class="content">
        <h2>{{ $reportName }}</h2>
        
        @if($reportDescription)
            <p><strong>Descripción:</strong> {{ $reportDescription }}</p>
        @endif

        <div class="info-row">
            <span class="info-label">Frecuencia:</span>
            <span class="info-value">{{ $frequency }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Generado el:</span>
            <span class="info-value">{{ $generatedAt }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Creado por:</span>
            <span class="info-value">{{ $userName }}</span>
        </div>
    </div>

    <div class="attachment-notice">
        <h3>📎 Archivo Adjunto</h3>
        <p>El reporte se encuentra adjunto a este correo electrónico. Puede descargarlo y abrirlo con el software apropiado.</p>
    </div>

    <div class="footer">
        <p>Este es un correo automático generado por el Sistema de Gestión Documental.</p>
        <p>Por favor, no responda a este mensaje.</p>
    </div>
</body>
</html>