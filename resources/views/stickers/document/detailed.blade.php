<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sticker Detallado - {{ $document_number }}</title>
    <style>
        @page {
            size: 80mm 100mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            width: 80mm;
            height: 100mm;
            padding: 4mm;
            display: flex;
            flex-direction: column;
        }
        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 3mm;
            border-bottom: 1px solid #333;
            padding-bottom: 2mm;
        }
        .company {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .doc-number {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-grow: 1;
        }
        .left-section {
            width: 48%;
        }
        .right-section {
            width: 48%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .qrcode {
            width: 35mm;
            height: 35mm;
            margin-bottom: 2mm;
        }
        .qrcode img {
            width: 100%;
            height: 100%;
        }
        .barcode {
            width: 35mm;
            height: 15mm;
            margin-bottom: 2mm;
        }
        .barcode img {
            max-width: 100%;
            max-height: 100%;
        }
        .info-row {
            margin-bottom: 2mm;
            font-size: 8pt;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .info-value {
            margin-top: 0.5mm;
            word-wrap: break-word;
        }
        .footer {
            width: 100%;
            font-size: 6pt;
            text-align: center;
            color: #999;
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company }}</div>
        <div class="doc-number">{{ $document_number }}</div>
    </div>

    <div class="content">
        <div class="left-section">
            <div class="info-row">
                <div class="info-label">Título</div>
                <div class="info-value">{{ $title }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Ubicación</div>
                <div class="info-value">{{ $location }}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Fecha</div>
                <div class="info-value">{{ $created_at }}</div>
            </div>

            @if(isset($document->category))
            <div class="info-row">
                <div class="info-label">Categoría</div>
                <div class="info-value">{{ $document->category->name }}</div>
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
        Sistema de Gestión Documental - Archive Master<br>
        Generado: {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
