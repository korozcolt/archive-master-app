<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Etiqueta - {{ $code }}</title>
    <style>
        @media screen {
            body {
                font-family: Arial, sans-serif;
                background: #f3f4f6;
                margin: 0;
                padding: 20px;
            }
            .print-controls {
                max-width: 600px;
                margin: 0 auto 20px;
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .print-controls h2 {
                margin: 0 0 12px;
                font-size: 18px;
                color: #111;
            }
            .print-controls .meta {
                font-size: 13px;
                color: #666;
                margin-bottom: 16px;
                line-height: 1.6;
            }
            .print-controls .meta strong { color: #333; }
            .btn-print {
                display: inline-block;
                background: #2563eb;
                color: white;
                border: none;
                padding: 12px 32px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                margin-right: 8px;
            }
            .btn-print:hover { background: #1d4ed8; }
            .btn-close {
                display: inline-block;
                background: #6b7280;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 14px;
                cursor: pointer;
            }
            .btn-close:hover { background: #4b5563; }
            .preview-frame {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                text-align: center;
            }
            .preview-frame h3 {
                margin: 0 0 12px;
                font-size: 14px;
                color: #888;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .sticker-preview {
                display: inline-block;
                border: 2px dashed #d1d5db;
            }
        }

        @media print {
            .print-controls { display: none !important; }
            .preview-frame { box-shadow: none; padding: 0; }
            .preview-frame h3 { display: none; }
            .sticker-preview { border: none; }
        }

        @page {
            size: {{ $pageSize['width'] }}mm {{ $pageSize['height'] }}mm;
            margin: 0;
        }

        .sticker {
            width: {{ $pageSize['width'] }}mm;
            height: {{ $pageSize['height'] }}mm;
            padding: 3mm;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .sticker .header { width: 100%; text-align: center; margin-bottom: 1mm; }
        .sticker .company { font-size: 7pt; font-weight: bold; margin-bottom: 1mm; }
        .sticker .code { font-size: 10pt; font-weight: bold; }
        .sticker .qrcode { width: 26mm; height: 26mm; margin: 1mm 0; }
        .sticker .qrcode img { width: 100%; height: 100%; }
        .sticker .barcode { width: 42mm; height: 10mm; margin: 1mm 0; text-align: center; }
        .sticker .barcode img { max-width: 100%; max-height: 100%; }
        .sticker .info { width: 100%; font-size: 6pt; text-align: center; }
        .sticker .full-path { font-weight: bold; margin-bottom: 0.5mm; }
        .sticker .capacity { font-size: 5.5pt; color: #666; }
        .sticker .footer { width: 100%; font-size: 5pt; text-align: center; color: #999; }
    </style>
</head>
<body>
    <div class="print-controls">
        <h2>Imprimir Etiqueta de Ubicacion Fisica</h2>
        <div class="meta">
            <strong>Codigo:</strong> {{ $code }}<br>
            <strong>Ruta:</strong> {{ $full_path }}<br>
            <strong>Capacidad:</strong> {{ $capacity }} ({{ $capacity_percentage }}%)<br>
            <strong>Empresa:</strong> {{ $company }}<br>
            <strong>Plantilla:</strong> {{ ucfirst($template) }} ({{ $pageSize['width'] }}mm x {{ $pageSize['height'] }}mm)
        </div>
        <button class="btn-print" onclick="window.print()">Imprimir Etiqueta</button>
        <a class="btn-pdf" href="{{ route('stickers.locations.download', ['location' => $location->id, 'template' => $template]) }}" style="display:inline-block;background:#059669;color:white;border:none;padding:12px 24px;border-radius:6px;font-size:14px;cursor:pointer;text-decoration:none;margin-right:8px;">Guardar PDF</a>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <div class="preview-frame">
        <h3>Vista previa</h3>
        <div class="sticker-preview">
            <div class="sticker">
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
                    <div class="full-path">{{ $full_path }}</div>
                    <div class="capacity">Capacidad: {{ $capacity }} ({{ $capacity_percentage }}%)</div>
                </div>
                <div class="footer">{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <script>
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 500);
            });
        }
    </script>
</body>
</html>
