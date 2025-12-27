<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sticker Ubicación - {{ $code }}</title>
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
            background: #f9f9f9;
            border: 1px solid #333;
        }
        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 2mm;
        }
        .company {
            font-size: 7pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .code {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
            background: #333;
            color: white;
            padding: 1mm;
        }
        .qrcode {
            width: 30mm;
            height: 30mm;
            margin: 2mm 0;
            background: white;
            padding: 1mm;
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
            background: white;
            padding: 1mm;
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
        .path {
            font-weight: bold;
            margin-bottom: 1mm;
            text-overflow: ellipsis;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .capacity {
            font-size: 8pt;
            color: #333;
            font-weight: bold;
        }
        .capacity-bar {
            width: 100%;
            height: 3mm;
            background: #e0e0e0;
            margin-top: 1mm;
            position: relative;
        }
        .capacity-fill {
            height: 100%;
            background: #4CAF50;
        }
        .footer {
            width: 100%;
            font-size: 5pt;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company }}</div>
        <div class="code">{{ $code }}</div>
    </div>

    <div class="qrcode">
        <img src="{{ $qrcode }}" alt="QR Code">
    </div>

    <div class="barcode">
        <img src="{{ $barcode }}" alt="Barcode">
    </div>

    <div class="info">
        <div class="path">{{ $full_path }}</div>
        @if($location->capacity_total)
        <div class="capacity">Capacidad: {{ $capacity }}</div>
        <div class="capacity-bar">
            <div class="capacity-fill" style="width: {{ $capacity_percentage }}%"></div>
        </div>
        @endif
    </div>

    <div class="footer">
        UBICACIÓN FÍSICA
    </div>
</body>
</html>
