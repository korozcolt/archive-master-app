<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sticker Compacto - {{ $code }}</title>
    <style>
        @page { size: 40mm 40mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            width: 40mm; height: 40mm;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border: 1px solid #333;
        }
        .code {
            font-size: 6pt;
            font-weight: bold;
            margin-bottom: 1mm;
            text-align: center;
            background: #333;
            color: white;
            padding: 1mm;
            width: 100%;
        }
        .qrcode { width: 32mm; height: 32mm; background: white; padding: 1mm; }
        .qrcode img { width: 100%; height: 100%; }
    </style>
</head>
<body>
    <div class="code">{{ $code }}</div>
    <div class="qrcode">
        <img src="{{ $qrcode }}" alt="QR Code">
    </div>
</body>
</html>
