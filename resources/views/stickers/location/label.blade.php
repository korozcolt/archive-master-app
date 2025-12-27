<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta - {{ $code }}</title>
    <style>
        @page { size: 100mm 50mm; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            width: 100mm; height: 50mm;
            padding: 3mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f9f9f9;
            border: 1px solid #333;
        }
        .left-section { width: 65%; display: flex; flex-direction: column; justify-content: center; }
        .right-section { width: 32%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .company { font-size: 8pt; font-weight: bold; margin-bottom: 1mm; }
        .code {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2mm;
            background: #333;
            color: white;
            padding: 1mm;
        }
        .path { font-size: 8pt; margin-bottom: 2mm; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .capacity { font-size: 7pt; color: #666; margin-bottom: 1mm; }
        .capacity-bar { width: 100%; height: 3mm; background: #e0e0e0; margin-bottom: 1mm; }
        .capacity-fill { height: 100%; background: #4CAF50; }
        .barcode { width: 100%; height: 10mm; }
        .barcode img { max-width: 100%; max-height: 100%; }
        .qrcode { width: 28mm; height: 28mm; background: white; padding: 1mm; }
        .qrcode img { width: 100%; height: 100%; }
    </style>
</head>
<body>
    <div class="left-section">
        <div class="company">{{ $company }}</div>
        <div class="code">{{ $code }}</div>
        <div class="path">{{ $full_path }}</div>
        @if($location->capacity_total)
        <div class="capacity">Capacidad: {{ $capacity }}</div>
        <div class="capacity-bar">
            <div class="capacity-fill" style="width: {{ $capacity_percentage }}%"></div>
        </div>
        @endif
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
