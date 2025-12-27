<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sticker Compacto - {{ $document_number }}</title>
    <style>
        @page {
            size: 40mm 40mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            width: 40mm;
            height: 40mm;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .doc-number {
            font-size: 7pt;
            font-weight: bold;
            margin-bottom: 1mm;
            text-align: center;
        }
        .qrcode {
            width: 32mm;
            height: 32mm;
        }
        .qrcode img {
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="doc-number">{{ $document_number }}</div>
    <div class="qrcode">
        <img src="{{ $qrcode }}" alt="QR Code">
    </div>
</body>
</html>
