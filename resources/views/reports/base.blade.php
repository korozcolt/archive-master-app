<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Reporte' }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #1a202c;
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        
        .header .subtitle {
            color: #718096;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .filters {
            background-color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .filters h3 {
            margin: 0 0 10px 0;
            color: #2d3748;
            font-size: 14px;
        }
        
        .filters p {
            margin: 5px 0;
            color: #4a5568;
        }
        
        .content {
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        table th {
            background-color: #4a5568;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #2d3748;
        }
        
        table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        table tr:nth-child(even) {
            background-color: #f7fafc;
        }
        
        table tr:hover {
            background-color: #edf2f7;
        }
        
        .summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background-color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
            width: 23%;
        }
        
        .summary-card h4 {
            margin: 0 0 5px 0;
            color: #2d3748;
            font-size: 12px;
        }
        
        .summary-card .number {
            font-size: 18px;
            font-weight: bold;
            color: #3182ce;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 10px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background-color: #fed7d7;
            color: #742a2a;
        }
        
        .status-in-progress {
            background-color: #feebc8;
            color: #7b341e;
        }
        
        .status-overdue {
            background-color: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-sm { font-size: 11px; }
        .text-xs { font-size: 10px; }
        
        @page {
            margin: 2cm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title ?? 'Reporte del Sistema' }}</h1>
        <div class="subtitle">Generado el {{ $generated_at->format('d/m/Y H:i:s') }}</div>
        @if(isset($company))
            <div class="subtitle">{{ $company }}</div>
        @endif
    </div>

    @if(!empty($filters))
        <div class="filters">
            <h3>Filtros Aplicados:</h3>
            @if(isset($filters['date_from']))
                <p><strong>Fecha desde:</strong> {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}</p>
            @endif
            @if(isset($filters['date_to']))
                <p><strong>Fecha hasta:</strong> {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</p>
            @endif
            @if(isset($filters['department_id']))
                <p><strong>Departamento:</strong> {{ $filters['department_name'] ?? 'ID: ' . $filters['department_id'] }}</p>
            @endif
            @if(isset($filters['status_id']))
                <p><strong>Estado:</strong> {{ $filters['status_name'] ?? 'ID: ' . $filters['status_id'] }}</p>
            @endif
        </div>
    @endif

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        <p>ArchiveMaster - Sistema de Gestión Documental | Página <span class="pagenum"></span></p>
        <p>Este reporte fue generado automáticamente el {{ $generated_at->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>