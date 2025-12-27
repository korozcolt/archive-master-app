<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sticker Detallado - {{ $code }}</title>
    <style>
        @page { size: 80mm 100mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            width: 80mm; height: 100mm;
            padding: 4mm;
            display: flex;
            flex-direction: column;
            background: #f9f9f9;
            border: 2px solid #333;
        }
        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 3mm;
            border-bottom: 2px solid #333;
            padding-bottom: 2mm;
            background: #333;
            color: white;
            padding: 2mm;
        }
        .company { font-size: 9pt; font-weight: bold; margin-bottom: 1mm; }
        .code { font-size: 14pt; font-weight: bold; }
        .content { display: flex; justify-content: space-between; flex-grow: 1; }
        .left-section { width: 48%; }
        .right-section { width: 48%; display: flex; flex-direction: column; align-items: center; }
        .qrcode { width: 35mm; height: 35mm; margin-bottom: 2mm; background: white; padding: 2mm; }
        .qrcode img { width: 100%; height: 100%; }
        .barcode { width: 35mm; height: 15mm; background: white; padding: 1mm; }
        .barcode img { max-width: 100%; max-height: 100%; }
        .info-row { margin-bottom: 2mm; font-size: 8pt; }
        .info-label { font-weight: bold; color: #555; font-size: 7pt; text-transform: uppercase; }
        .info-value { margin-top: 0.5mm; word-wrap: break-word; }
        .capacity-bar { width: 100%; height: 4mm; background: #e0e0e0; margin-top: 1mm; position: relative; }
        .capacity-fill { height: 100%; background: #4CAF50; }
        .footer {
            width: 100%;
            font-size: 6pt;
            text-align: center;
            color: #666;
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company }}</div>
        <div class="code">{{ $code }}</div>
    </div>

    <div class="content">
        <div class="left-section">
            <div class="info-row">
                <div class="info-label">Ubicación Completa</div>
                <div class="info-value">{{ $full_path }}</div>
            </div>

            @if($location->capacity_total)
            <div class="info-row">
                <div class="info-label">Capacidad</div>
                <div class="info-value">{{ $capacity }} ({{ $capacity_percentage }}%)</div>
                <div class="capacity-bar">
                    <div class="capacity-fill" style="width: {{ $capacity_percentage }}%"></div>
                </div>
            </div>
            @endif

            @if($location->notes)
            <div class="info-row">
                <div class="info-label">Notas</div>
                <div class="info-value">{{ Str::limit($location->notes, 100) }}</div>
            </div>
            @endif
        </div>

        <div class="right-section">
            <div class="qrcode">
                <img src="{{ $qrcode }}" alt="QR Code">
            </div>
            <div class="barcode">
                <img src="{{ $barcode }}" alt="Barcode">
            </div>
        </div>
    </div>

    <div class="footer">
        UBICACIÓN FÍSICA - Sistema de Gestión Documental
    </div>
</body>
</html>
