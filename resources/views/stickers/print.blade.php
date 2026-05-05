<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Etiqueta - {{ $document_number }}</title>
    <style>
        /* === Estilos de pantalla (UI de control) === */
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
            .print-controls .meta strong {
                color: #333;
            }
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
                padding: 0;
                background: white;
            }
        }

        /* === Ocultar controles al imprimir === */
        @media print {
            .print-controls { display: none !important; }
            .preview-frame { box-shadow: none; padding: 0; }
            .preview-frame h3 { display: none; }
            .sticker-preview { border: none; }
        }

        /* === Estilos del sticker (aplica en pantalla y en impresión) === */
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
            page-break-after: avoid;
        }

        @if($template === 'label')
        /* Label: horizontal 100mm x 50mm */
        .sticker {
            align-items: center;
            justify-content: space-between;
        }
        .sticker .left-section {
            width: 63%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .sticker .right-section {
            width: 34%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .sticker .company { font-size: 8pt; font-weight: bold; margin-bottom: 1mm; }
        .sticker .doc-number { font-size: 11pt; font-weight: bold; margin-bottom: 1.5mm; }
        .sticker .title { font-size: 8pt; margin-bottom: 1.5mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sticker .location { font-size: 7pt; color: #444; margin-bottom: 1.5mm; }
        .sticker .barcode { width: 100%; height: 10mm; }
        .sticker .barcode img { max-width: 100%; max-height: 100%; }
        .sticker .qrcode { width: 26mm; height: 26mm; }
        .sticker .qrcode img { width: 100%; height: 100%; }
        .sticker .date { font-size: 5pt; color: #999; text-align: center; margin-top: 1mm; }

        @elseif($template === 'compact')
        /* Compact: 40mm x 40mm */
        .sticker {
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .sticker .doc-number { font-size: 7pt; font-weight: bold; margin-bottom: 1mm; text-align: center; }
        .sticker .qrcode { width: 30mm; height: 30mm; }
        .sticker .qrcode img { width: 100%; height: 100%; }

        @elseif($template === 'detailed')
        /* Detailed: 80mm x 100mm */
        .sticker {
            flex-direction: column;
        }
        .sticker .header { width: 100%; text-align: center; margin-bottom: 2mm; border-bottom: 0.5pt solid #333; padding-bottom: 2mm; }
        .sticker .company { font-size: 9pt; font-weight: bold; margin-bottom: 1mm; }
        .sticker .doc-number { font-size: 12pt; font-weight: bold; }
        .sticker .content { display: flex; justify-content: space-between; flex-grow: 1; }
        .sticker .content .left-section { width: 48%; }
        .sticker .content .right-section { width: 48%; display: flex; flex-direction: column; align-items: center; }
        .sticker .info-row { margin-bottom: 2mm; font-size: 7pt; }
        .sticker .info-label { font-weight: bold; color: #555; font-size: 6pt; text-transform: uppercase; }
        .sticker .info-value { margin-top: 0.5mm; word-wrap: break-word; }
        .sticker .qrcode { width: 30mm; height: 30mm; margin-bottom: 2mm; }
        .sticker .qrcode img { width: 100%; height: 100%; }
        .sticker .barcode { width: 30mm; height: 12mm; }
        .sticker .barcode img { max-width: 100%; max-height: 100%; }
        .sticker .footer { width: 100%; font-size: 5pt; text-align: center; color: #999; margin-top: 2mm; padding-top: 1mm; border-top: 0.5pt solid #ddd; }

        @else
        /* Standard: 50mm x 80mm (default) */
        .sticker {
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .sticker .header { width: 100%; text-align: center; margin-bottom: 1mm; }
        .sticker .company { font-size: 7pt; font-weight: bold; margin-bottom: 1mm; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
        .sticker .doc-number { font-size: 9pt; font-weight: bold; }
        .sticker .qrcode { width: 26mm; height: 26mm; margin: 1mm 0; }
        .sticker .qrcode img { width: 100%; height: 100%; }
        .sticker .barcode { width: 42mm; height: 10mm; margin: 1mm 0; text-align: center; }
        .sticker .barcode img { max-width: 100%; max-height: 100%; }
        .sticker .info { width: 100%; font-size: 6pt; text-align: center; }
        .sticker .title { font-weight: bold; margin-bottom: 0.5mm; text-overflow: ellipsis; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .sticker .location { font-size: 5.5pt; color: #666; }
        .sticker .footer { width: 100%; font-size: 5pt; text-align: center; color: #999; }
        @endif
    </style>
</head>
<body>
    <div class="print-controls">
        <h2>Imprimir Etiqueta de Documento</h2>
        <div class="meta">
            <strong>Documento:</strong> {{ $document_number }}<br>
            <strong>Titulo:</strong> {{ Str::limit($title, 60) }}<br>
            <strong>Ubicacion:</strong> {{ $location }}<br>
            <strong>Empresa:</strong> {{ $company }}<br>
            <strong>Plantilla:</strong> {{ ucfirst($template) }} ({{ $pageSize['width'] }}mm x {{ $pageSize['height'] }}mm)
        </div>
        <button class="btn-print" onclick="window.print()">Imprimir Etiqueta</button>
        <a class="btn-pdf" href="{{ route('stickers.documents.download', ['document' => $document->id, 'template' => $template]) }}" style="display:inline-block;background:#059669;color:white;border:none;padding:12px 24px;border-radius:6px;font-size:14px;cursor:pointer;text-decoration:none;margin-right:8px;">Guardar PDF</a>
        <button class="btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <div class="preview-frame">
        <h3>Vista previa</h3>
        <div class="sticker-preview">
            <div class="sticker">
                @if($template === 'label')
                    <div class="left-section">
                        @if($options['include_company'] ?? true)
                        <div class="company">{{ $company }}</div>
                        @endif
                        <div class="doc-number">{{ $document_number }}</div>
                        <div class="title">{{ Str::limit($title, 50) }}</div>
                        @if($location !== 'Sin ubicacion asignada')
                        <div class="location">{{ $location }}</div>
                        @endif
                        <div class="barcode">
                            <img src="{{ $barcode }}" alt="Barcode">
                        </div>
                    </div>
                    <div class="right-section">
                        <div class="qrcode">
                            <img src="{{ $qrcode }}" alt="QR Code">
                        </div>
                        @if($options['include_date'] ?? true)
                        <div class="date">{{ $created_at }}</div>
                        @endif
                    </div>

                @elseif($template === 'compact')
                    <div class="doc-number">{{ $document_number }}</div>
                    <div class="qrcode">
                        <img src="{{ $qrcode }}" alt="QR Code">
                    </div>

                @elseif($template === 'detailed')
                    <div class="header">
                        <div class="company">{{ $company }}</div>
                        <div class="doc-number">{{ $document_number }}</div>
                    </div>
                    <div class="content">
                        <div class="left-section">
                            <div class="info-row">
                                <div class="info-label">Titulo</div>
                                <div class="info-value">{{ $title }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Ubicacion</div>
                                <div class="info-value">{{ $location }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha</div>
                                <div class="info-value">{{ $created_at }}</div>
                            </div>
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
                    <div class="footer">Archive Master - {{ now()->format('d/m/Y H:i') }}</div>

                @else
                    {{-- Standard --}}
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
                        @if($location !== 'Sin ubicacion asignada')
                        <div class="location">{{ $location }}</div>
                        @endif
                    </div>
                    @if($options['include_date'] ?? true)
                    <div class="footer">{{ $created_at }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <script>
        // Auto-imprimir si viene con el parametro ?auto_print=1
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 500);
            });
        }
    </script>
</body>
</html>
