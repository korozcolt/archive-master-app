<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stickers Batch</title>
    <style>
        @page {
            size: A4 {{ $options['orientation'] ?? 'portrait' }};
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
        }
        .sticker-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5mm;
            page-break-inside: avoid;
        }
        .sticker {
            border: 1px dashed #999;
            padding: 3mm;
            page-break-inside: avoid;
            min-height: 80mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .sticker-header {
            width: 100%;
            text-align: center;
            margin-bottom: 2mm;
        }
        .sticker-company {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        .sticker-code {
            font-size: 10pt;
            font-weight: bold;
        }
        .sticker-qr {
            width: 40mm;
            height: 40mm;
        }
        .sticker-qr img {
            width: 100%;
            height: 100%;
        }
        .sticker-barcode {
            width: 100%;
            height: 15mm;
            text-align: center;
        }
        .sticker-barcode img {
            max-width: 100%;
            max-height: 100%;
        }
        .sticker-info {
            width: 100%;
            font-size: 7pt;
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="sticker-grid">
        @foreach($stickers as $index => $sticker)
        <div class="sticker">
            <div class="sticker-header">
                <div class="sticker-company">{{ $sticker['company'] ?? '' }}</div>
                <div class="sticker-code">
                    @if($sticker['type'] === 'document')
                        {{ $sticker['document_number'] }}
                    @else
                        {{ $sticker['code'] }}
                    @endif
                </div>
            </div>

            <div class="sticker-qr">
                <img src="{{ $sticker['qrcode'] }}" alt="QR Code">
            </div>

            <div class="sticker-barcode">
                <img src="{{ $sticker['barcode'] }}" alt="Barcode">
            </div>

            <div class="sticker-info">
                @if($sticker['type'] === 'document')
                    {{ Str::limit($sticker['title'], 40) }}
                @else
                    {{ Str::limit($sticker['full_path'], 40) }}
                @endif
            </div>
        </div>

        @if(($index + 1) % 9 == 0 && $index + 1 < count($stickers))
        </div>
        <div class="page-break"></div>
        <div class="sticker-grid">
        @endif
        @endforeach
    </div>
</body>
</html>
