@extends('reports.base')

@section('title', 'Reporte de Documentos por Departamento')

@section('content')
<div class="report-summary">
    <div class="summary-grid">
        <div class="summary-item">
            <h3>Total Departamentos</h3>
            <p class="summary-value">{{ $data->count() }}</p>
        </div>
        <div class="summary-item">
            <h3>Departamentos Activos</h3>
            <p class="summary-value">{{ $data->where('documents_count', '>', 0)->count() }}</p>
        </div>
        <div class="summary-item">
            <h3>Total Documentos</h3>
            <p class="summary-value">{{ $data->sum('documents_count') }}</p>
        </div>
        <div class="summary-item">
            <h3>Promedio por Departamento</h3>
            <p class="summary-value">{{ $data->count() > 0 ? round($data->sum('documents_count') / $data->count(), 1) : 0 }}</p>
        </div>
    </div>
</div>

<div class="section">
    <h2>Distribución por Departamento</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Departamento</th>
                <th>Total Documentos</th>
                <th>Documentos Pendientes</th>
                <th>Documentos Completados</th>
                <th>Documentos Rechazados</th>
                <th>% del Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $department)
            <tr>
                <td>{{ $department->name }}</td>
                <td class="text-center">{{ $department->documents_count ?? 0 }}</td>
                <td class="text-center">{{ $department->pending_count ?? 0 }}</td>
                <td class="text-center">{{ $department->completed_count ?? 0 }}</td>
                <td class="text-center">{{ $department->rejected_count ?? 0 }}</td>
                <td class="text-center">
                    {{ $data->sum('documents_count') > 0 ? round(($department->documents_count / $data->sum('documents_count')) * 100, 1) : 0 }}%
                </td>
                <td>
                    @if($department->documents_count > 0)
                        <span class="status-badge status-active">Activo</span>
                    @else
                        <span class="status-badge status-inactive">Inactivo</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">No hay datos de departamentos para mostrar</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($data->count() > 0)
<div class="section">
    <h2>Ranking de Departamentos por Volumen</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Posición</th>
                <th>Departamento</th>
                <th>Total Documentos</th>
                <th>Tasa de Completado</th>
                <th>Tasa de Rechazo</th>
                <th>Eficiencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->sortByDesc('documents_count') as $index => $department)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $department->name }}</td>
                <td class="text-center">{{ $department->documents_count }}</td>
                <td class="text-center">
                    {{ $department->documents_count > 0 ? round(($department->completed_count / $department->documents_count) * 100, 1) : 0 }}%
                </td>
                <td class="text-center">
                    {{ $department->documents_count > 0 ? round(($department->rejected_count / $department->documents_count) * 100, 1) : 0 }}%
                </td>
                <td class="text-center">
                    @php
                        $efficiency = $department->documents_count > 0 ? 
                            round((($department->completed_count / $department->documents_count) * 100) - 
                                  (($department->rejected_count / $department->documents_count) * 100), 1) : 0;
                    @endphp
                    <span class="efficiency-badge efficiency-{{ $efficiency >= 80 ? 'high' : ($efficiency >= 60 ? 'medium' : 'low') }}">
                        {{ $efficiency }}%
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <h2>Análisis de Eficiencia por Departamento</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Departamento</th>
                <th>Tiempo Promedio de Procesamiento</th>
                <th>Documentos en SLA</th>
                <th>Documentos Fuera de SLA</th>
                <th>Cumplimiento SLA</th>
                <th>Clasificación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->where('documents_count', '>', 0) as $department)
            <tr>
                <td>{{ $department->name }}</td>
                <td class="text-center">{{ $department->avg_processing_time ?? 'N/A' }} días</td>
                <td class="text-center">{{ $department->sla_compliant_count ?? 0 }}</td>
                <td class="text-center">{{ $department->sla_overdue_count ?? 0 }}</td>
                <td class="text-center">
                    @php
                        $slaTotal = ($department->sla_compliant_count ?? 0) + ($department->sla_overdue_count ?? 0);
                        $slaCompliance = $slaTotal > 0 ? round(($department->sla_compliant_count / $slaTotal) * 100, 1) : 0;
                    @endphp
                    {{ $slaCompliance }}%
                </td>
                <td class="text-center">
                    @if($slaCompliance >= 90)
                        <span class="status-badge status-excellent">Excelente</span>
                    @elseif($slaCompliance >= 75)
                        <span class="status-badge status-good">Bueno</span>
                    @elseif($slaCompliance >= 60)
                        <span class="status-badge status-regular">Regular</span>
                    @else
                        <span class="status-badge status-poor">Necesita Mejora</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Resumen Ejecutivo</h2>
    <div class="executive-summary">
        <p><strong>Análisis Departamental:</strong></p>
        <ul>
            <li>Se analizaron {{ $data->count() }} departamentos en el período seleccionado.</li>
            <li>{{ $data->where('documents_count', '>', 0)->count() }} departamentos ({{ $data->count() > 0 ? round(($data->where('documents_count', '>', 0)->count() / $data->count()) * 100, 1) : 0 }}%) procesaron documentos.</li>
            <li>El volumen total de documentos fue de {{ $data->sum('documents_count') }} documentos.</li>
            @if($data->count() > 0)
                @php
                    $topDepartment = $data->sortByDesc('documents_count')->first();
                    $totalCompleted = $data->sum('completed_count');
                    $totalDocuments = $data->sum('documents_count');
                    $overallCompletionRate = $totalDocuments > 0 ? round(($totalCompleted / $totalDocuments) * 100, 1) : 0;
                @endphp
                <li>El departamento con mayor volumen es <strong>{{ $topDepartment->name }}</strong> con {{ $topDepartment->documents_count }} documentos.</li>
                <li>La tasa general de completado es del {{ $overallCompletionRate }}%.</li>
            @endif
        </ul>
        
        <p><strong>Recomendaciones:</strong></p>
        <ul>
            @if($data->where('documents_count', 0)->count() > 0)
                <li>Revisar la asignación de trabajo en los {{ $data->where('documents_count', 0)->count() }} departamentos sin actividad.</li>
            @endif
            <li>Implementar mejores prácticas de los departamentos más eficientes en aquellos con menor rendimiento.</li>
            <li>Considerar redistribución de carga de trabajo entre departamentos.</li>
            <li>Establecer metas específicas de SLA por departamento.</li>
            <li>Implementar programas de capacitación para departamentos con baja eficiencia.</li>
        </ul>
    </div>
</div>
@endsection

<style>
.efficiency-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
    color: white;
}

.efficiency-high {
    background-color: #10b981;
}

.efficiency-medium {
    background-color: #f59e0b;
}

.efficiency-low {
    background-color: #ef4444;
}

.status-excellent {
    background-color: #10b981;
    color: white;
}

.status-good {
    background-color: #3b82f6;
    color: white;
}

.status-regular {
    background-color: #f59e0b;
    color: white;
}

.status-poor {
    background-color: #ef4444;
    color: white;
}
</style>