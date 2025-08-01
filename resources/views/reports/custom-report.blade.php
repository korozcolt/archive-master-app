<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportName ?? 'Reporte Personalizado' }}</title>
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
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #007bff;
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .report-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
        }
        
        .report-info h3 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 16px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .summary-section {
            margin-bottom: 30px;
        }
        
        .summary-section h3 {
            color: #007bff;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .metric-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background-color: #fff;
        }
        
        .data-table th {
            background-color: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 11px;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .data-table tr:hover {
            background-color: #e3f2fd;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .filters-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }
        
        .filters-section h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
        }
        
        .filter-item {
            display: inline-block;
            background-color: #fff;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 15px;
            border: 1px solid #ced4da;
            font-size: 10px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .chart-placeholder {
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 40px;
            text-align: center;
            color: #6c757d;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $reportName ?? 'Reporte Personalizado' }}</h1>
        <div class="subtitle">Sistema de Gestión Documental</div>
        <div class="subtitle">Generado el {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <!-- Report Information -->
    <div class="report-info">
        <h3>Información del Reporte</h3>
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">Tipo de Reporte:</span>
                    <span class="info-value">{{ ucfirst($reportType ?? 'Personalizado') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Período:</span>
                    <span class="info-value">
                        @if(isset($dateFrom) && isset($dateTo))
                            {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                        @else
                            Todos los registros
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total de Registros:</span>
                    <span class="info-value">{{ $data->count() }}</span>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Generado por:</span>
                    <span class="info-value">{{ auth()->user()->name ?? 'Sistema' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Formato:</span>
                    <span class="info-value">PDF</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Versión:</span>
                    <span class="info-value">1.0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Applied Filters -->
    @if(isset($appliedFilters) && count($appliedFilters) > 0)
    <div class="filters-section">
        <h4>Filtros Aplicados:</h4>
        @foreach($appliedFilters as $filter)
            <span class="filter-item">
                {{ $filter['field'] }} {{ $filter['operator'] }} {{ $filter['value'] ?? 'N/A' }}
            </span>
        @endforeach
    </div>
    @endif

    <!-- Summary Metrics -->
    @if(isset($aggregates) && count($aggregates) > 0)
    <div class="summary-section">
        <h3>Resumen Ejecutivo</h3>
        <div class="metrics-grid">
            @foreach($aggregates as $key => $value)
                <div class="metric-card">
                    <div class="metric-value">
                        @if(is_numeric($value))
                            {{ number_format($value, 0, ',', '.') }}
                        @elseif(is_array($value))
                            {{ count($value) }}
                        @else
                            {{ $value }}
                        @endif
                    </div>
                    <div class="metric-label">{{ str_replace('_', ' ', ucwords($key)) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Data Table -->
    <div class="summary-section">
        <h3>Datos Detallados</h3>
        
        @if($data->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        @if($reportType === 'documents' || !isset($reportType))
                            <th>ID</th>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Categoría</th>
                            <th>Departamento</th>
                            <th>Usuario</th>
                            <th>Fecha Creación</th>
                            <th>Última Actualización</th>
                        @elseif($reportType === 'users')
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Departamento</th>
                            <th>Estado</th>
                            <th>Documentos</th>
                            <th>Fecha Registro</th>
                        @elseif($reportType === 'departments')
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Usuarios</th>
                            <th>Documentos</th>
                            <th>Fecha Creación</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $item)
                        <tr>
                            @if($reportType === 'documents' || !isset($reportType))
                                <td>{{ $item->id }}</td>
                                <td>{{ Str::limit($item->title ?? 'Sin título', 30) }}</td>
                                <td>
                                    <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $item->status->name ?? 'unknown')) }}">
                                        {{ $item->status->name ?? 'Sin estado' }}
                                    </span>
                                </td>
                                <td>{{ $item->category->name ?? 'Sin categoría' }}</td>
                                <td>{{ $item->department->name ?? 'Sin departamento' }}</td>
                                <td>{{ $item->user->name ?? 'Sin usuario' }}</td>
                                <td>{{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : 'N/A' }}</td>
                                <td>{{ $item->updated_at ? $item->updated_at->format('d/m/Y H:i') : 'N/A' }}</td>
                            @elseif($reportType === 'users')
                                <td>{{ $item->id }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->email }}</td>
                                <td>{{ $item->department->name ?? 'Sin departamento' }}</td>
                                <td>
                                    <span class="status-badge {{ $item->is_active ? 'status-completed' : 'status-cancelled' }}">
                                        {{ $item->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>{{ $item->documents_count ?? $item->documents->count() }}</td>
                                <td>{{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : 'N/A' }}</td>
                            @elseif($reportType === 'departments')
                                <td>{{ $item->id }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ Str::limit($item->description ?? 'Sin descripción', 40) }}</td>
                                <td>{{ $item->users_count ?? $item->users->count() }}</td>
                                <td>{{ $item->documents_count ?? $item->documents->count() }}</td>
                                <td>{{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : 'N/A' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-data">
                <p>No se encontraron datos que coincidan con los criterios especificados.</p>
                <p>Intente ajustar los filtros o el rango de fechas.</p>
            </div>
        @endif
    </div>

    <!-- Chart Placeholder -->
    @if(isset($chartData) && $chartData)
    <div class="page-break">
        <div class="summary-section">
            <h3>Análisis Gráfico</h3>
            <div class="chart-placeholder">
                <p><strong>Gráfico de Datos</strong></p>
                <p>Los datos gráficos están disponibles en la versión interactiva del reporte.</p>
                <p>Para visualizaciones detalladas, acceda al panel de administración.</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>Este reporte fue generado automáticamente por el Sistema de Gestión Documental.</p>
        <p>Para más información, contacte al administrador del sistema.</p>
        <p>Página generada el {{ now()->format('d/m/Y H:i:s') }} | Versión del sistema: 1.0</p>
    </div>
</body>
</html>