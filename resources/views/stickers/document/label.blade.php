<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta - {{ $document_number }}</title>
    <style>
        @page {
            size: 100mm 50mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            width: 100mm;
            height: 50mm;
            padding: 3mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .left-section {
            width: 65%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .right-section {
            width: 32%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .company {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .doc-number {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        .title {
            font-size: 9pt;
            margin-bottom: 2mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .location {
            font-size: 7pt;
            color: #666;
        }
        .barcode {
            width: 100%;
            height: 12mm;
            margin-bottom: 1mm;
        }
        .barcode img {
            max-width: 100%;
            max-height: 100%;
        }
        .qrcode {
            width: 28mm;
            height: 28mm;
        }
        .qrcode img {
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="left-section">
        <div class="company">{{ $company }}</div>
        <div class="doc-number">{{ $document_number }}</div>
        <div class="title">{{ $title }}</div>
        <div class="location">📍 {{ $location }}</div>
        <div class="barcode">
            <img src="{{ $barcode }}" alt="Barcode">
        </div>
    </div>

    <div class="right-section">
        <div class="qrcode">
            <img src="{{ $qrcode }}" alt="QR Code">
        </div>
    </div>
</body>
</html>
