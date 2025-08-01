@extends('reports.base')

@section('title', 'Reporte de Actividad por Usuario')

@section('content')
<div class="report-summary">
    <div class="summary-grid">
        <div class="summary-item">
            <h3>Total de Usuarios</h3>
            <p class="summary-value">{{ $data->count() }}</p>
        </div>
        <div class="summary-item">
            <h3>Usuarios Activos</h3>
            <p class="summary-value">{{ $data->where('documents_count', '>', 0)->count() }}</p>
        </div>
        <div class="summary-item">
            <h3>Total Documentos</h3>
            <p class="summary-value">{{ $data->sum('documents_count') }}</p>
        </div>
        <div class="summary-item">
            <h3>Promedio por Usuario</h3>
            <p class="summary-value">{{ $data->count() > 0 ? round($data->sum('documents_count') / $data->count(), 1) : 0 }}</p>
        </div>
    </div>
</div>

<div class="section">
    <h2>Actividad por Usuario</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Email</th>
                <th>Departamento</th>
                <th>Documentos Creados</th>
                <th>Documentos Procesados</th>
                <th>Última Actividad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->department->name ?? 'Sin departamento' }}</td>
                <td class="text-center">{{ $user->documents_count ?? 0 }}</td>
                <td class="text-center">{{ $user->processed_documents_count ?? 0 }}</td>
                <td>{{ $user->last_activity ? $user->last_activity->format('d/m/Y H:i') : 'Sin actividad' }}</td>
                <td>
                    @if($user->documents_count > 0)
                        <span class="status-badge status-active">Activo</span>
                    @else
                        <span class="status-badge status-inactive">Inactivo</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">No hay datos de actividad para mostrar</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($data->count() > 0)
<div class="section">
    <h2>Top 10 Usuarios Más Activos</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Posición</th>
                <th>Usuario</th>
                <th>Departamento</th>
                <th>Total Documentos</th>
                <th>Porcentaje del Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->sortByDesc('documents_count')->take(10) as $index => $user)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->department->name ?? 'Sin departamento' }}</td>
                <td class="text-center">{{ $user->documents_count }}</td>
                <td class="text-center">
                    {{ $data->sum('documents_count') > 0 ? round(($user->documents_count / $data->sum('documents_count')) * 100, 1) : 0 }}%
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <h2>Resumen Ejecutivo</h2>
    <div class="executive-summary">
        <p><strong>Análisis de Actividad:</strong></p>
        <ul>
            <li>Se registraron {{ $data->count() }} usuarios en el sistema durante el período analizado.</li>
            <li>{{ $data->where('documents_count', '>', 0)->count() }} usuarios ({{ $data->count() > 0 ? round(($data->where('documents_count', '>', 0)->count() / $data->count()) * 100, 1) : 0 }}%) mostraron actividad.</li>
            <li>El promedio de documentos por usuario activo es de {{ $data->where('documents_count', '>', 0)->count() > 0 ? round($data->sum('documents_count') / $data->where('documents_count', '>', 0)->count(), 1) : 0 }}.</li>
            @if($data->count() > 0)
                @php
                    $topUser = $data->sortByDesc('documents_count')->first();
                @endphp
                <li>El usuario más activo es <strong>{{ $topUser->name }}</strong> con {{ $topUser->documents_count }} documentos.</li>
            @endif
        </ul>
        
        <p><strong>Recomendaciones:</strong></p>
        <ul>
            @if($data->where('documents_count', 0)->count() > 0)
                <li>Considerar capacitación para los {{ $data->where('documents_count', 0)->count() }} usuarios inactivos.</li>
            @endif
            <li>Implementar programas de reconocimiento para usuarios más productivos.</li>
            <li>Analizar patrones de uso para optimizar flujos de trabajo.</li>
        </ul>
    </div>
</div>
@endsection