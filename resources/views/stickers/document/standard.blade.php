<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sticker - {{ $document_number }}</title>
    <style>
        @page {
            size: 50mm 80mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            width: 50mm;
            height: 80mm;
            padding: 3mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 2mm;
        }
        .company {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1mm;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        .doc-number {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .qrcode {
            width: 30mm;
            height: 30mm;
            margin: 2mm 0;
        }
        .qrcode img {
            width: 100%;
            height: 100%;
        }
        .barcode {
            width: 44mm;
            height: 12mm;
            margin: 2mm 0;
            text-align: center;
        }
        .barcode img {
            max-width: 100%;
            max-height: 100%;
        }
        .info {
            width: 100%;
            font-size: 7pt;
            text-align: center;
        }
        .title {
            font-weight: bold;
            margin-bottom: 1mm;
            text-overflow: ellipsis;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .location {
            font-size: 6pt;
            color: #666;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        .footer {
            width: 100%;
            font-size: 5pt;
            text-align: center;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($options['include_company'] ?? true)
        <div class="company">{{ $company }}</div>
        @endif
        <div class="doc-number">{{ $document_number }}</div>
    </div>

    <div class="qrcode">
        <img src="{{ $qrcode }}" alt="QR Code">
    </div>

    <div class="barcode">
        <img src="{{ $barcode }}" alt="Barcode">
    </div>

    <div class="info">
        <div class="title">{{ Str::limit($title, 50) }}</div>
        @if($location !== 'Sin ubicación asignada')
        <div class="location">📍 {{ $location }}</div>
        @endif
    </div>

    @if($options['include_date'] ?? true)
    <div class="footer">
        Generado: {{ $created_at }}
    </div>
    @endif
</body>
</html>
