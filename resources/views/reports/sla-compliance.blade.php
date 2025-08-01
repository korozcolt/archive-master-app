@extends('reports.base')

@section('content')
    @php
        $totalDocuments = $data->count();
        $onTimeDocuments = $data->where('sla_status', 'On Time')->count();
        $overdueDocuments = $data->where('sla_status', 'Overdue')->count();
        $completedDocuments = $data->where('sla_status', 'Completed')->count();
        $noSlaDocuments = $data->where('sla_status', 'No SLA')->count();
        
        $complianceRate = $totalDocuments > 0 ? (($onTimeDocuments + $completedDocuments) / $totalDocuments) * 100 : 0;
    @endphp

    <div class="summary">
        <div class="summary-card">
            <h4>Total Documentos</h4>
            <div class="number">{{ number_format($totalDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Cumplimiento SLA</h4>
            <div class="number" style="color: {{ $complianceRate >= 80 ? '#22543d' : ($complianceRate >= 60 ? '#7b341e' : '#742a2a') }}">
                {{ number_format($complianceRate, 1) }}%
            </div>
        </div>
        <div class="summary-card">
            <h4>Documentos Vencidos</h4>
            <div class="number" style="color: #742a2a;">{{ number_format($overdueDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>A Tiempo</h4>
            <div class="number" style="color: #22543d;">{{ number_format($onTimeDocuments) }}</div>
        </div>
    </div>

    <h2>Análisis de Cumplimiento SLA</h2>
    
    <table>
        <thead>
            <tr>
                <th>Estado SLA</th>
                <th>Cantidad</th>
                <th>Porcentaje</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <span class="status-badge status-completed">
                        A Tiempo
                    </span>
                </td>
                <td class="text-center font-bold">{{ number_format($onTimeDocuments) }}</td>
                <td class="text-center">{{ $totalDocuments > 0 ? number_format(($onTimeDocuments / $totalDocuments) * 100, 1) : 0 }}%</td>
                <td class="text-sm">Documentos dentro del plazo establecido</td>
            </tr>
            <tr>
                <td>
                    <span class="status-badge status-overdue">
                        Vencidos
                    </span>
                </td>
                <td class="text-center font-bold">{{ number_format($overdueDocuments) }}</td>
                <td class="text-center">{{ $totalDocuments > 0 ? number_format(($overdueDocuments / $totalDocuments) * 100, 1) : 0 }}%</td>
                <td class="text-sm">Documentos que excedieron el plazo SLA</td>
            </tr>
            <tr>
                <td>
                    <span class="status-badge status-completed">
                        Completados
                    </span>
                </td>
                <td class="text-center font-bold">{{ number_format($completedDocuments) }}</td>
                <td class="text-center">{{ $totalDocuments > 0 ? number_format(($completedDocuments / $totalDocuments) * 100, 1) : 0 }}%</td>
                <td class="text-sm">Documentos finalizados exitosamente</td>
            </tr>
            <tr>
                <td>
                    <span class="status-badge status-pending">
                        Sin SLA
                    </span>
                </td>
                <td class="text-center font-bold">{{ number_format($noSlaDocuments) }}</td>
                <td class="text-center">{{ $totalDocuments > 0 ? number_format(($noSlaDocuments / $totalDocuments) * 100, 1) : 0 }}%</td>
                <td class="text-sm">Documentos sin fecha de vencimiento definida</td>
            </tr>
        </tbody>
    </table>

    @if($overdueDocuments > 0)
        <div class="page-break"></div>
        <h2>Documentos Vencidos (Requieren Atención Inmediata)</h2>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Estado Actual</th>
                    <th>Usuario Asignado</th>
                    <th>Departamento</th>
                    <th>Fecha Vencimiento</th>
                    <th>Días Vencido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data->where('sla_status', 'Overdue')->sortBy('due_date') as $document)
                    <tr>
                        <td class="text-center">{{ $document->id }}</td>
                        <td>{{ Str::limit($document->title, 25) }}</td>
                        <td>
                            <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $document->status->name ?? 'unknown')) }}">
                                {{ $document->status->name ?? 'Sin estado' }}
                            </span>
                        </td>
                        <td>{{ $document->user->name ?? 'Sin asignar' }}</td>
                        <td>{{ $document->department->name ?? 'Sin departamento' }}</td>
                        <td class="text-center">
                            {{ $document->due_date ? $document->due_date->format('d/m/Y') : 'Sin fecha' }}
                        </td>
                        <td class="text-center font-bold" style="color: #742a2a;">
                            @if($document->due_date)
                                {{ $document->due_date->diffInDays(now()) }} días
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($data->isNotEmpty())
        <div class="page-break"></div>
        <h2>Detalle Completo de Documentos</h2>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Estado SLA</th>
                    <th>Estado Actual</th>
                    <th>Categoría</th>
                    <th>Usuario</th>
                    <th>Departamento</th>
                    <th>Creado</th>
                    <th>Vencimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data->sortBy('due_date') as $document)
                    <tr>
                        <td class="text-center">{{ $document->id }}</td>
                        <td>{{ Str::limit($document->title, 20) }}</td>
                        <td>
                            <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $document->sla_status)) }}">
                                {{ $document->sla_status }}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $document->status->name ?? 'unknown')) }}">
                                {{ $document->status->name ?? 'Sin estado' }}
                            </span>
                        </td>
                        <td class="text-xs">{{ $document->category->name ?? 'Sin categoría' }}</td>
                        <td class="text-xs">{{ $document->user->name ?? 'Sin asignar' }}</td>
                        <td class="text-xs">{{ $document->department->name ?? 'Sin depto' }}</td>
                        <td class="text-center text-xs">{{ $document->created_at->format('d/m/Y') }}</td>
                        <td class="text-center text-xs">
                            @if($document->due_date)
                                {{ $document->due_date->format('d/m/Y') }}
                            @else
                                <span class="text-xs">Sin fecha</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div style="margin-top: 30px; padding: 15px; background-color: #f7fafc; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0; color: #2d3748;">Análisis de Cumplimiento SLA</h3>
        <p style="margin: 5px 0; color: #4a5568;">
            • <strong>Tasa de Cumplimiento General:</strong> {{ number_format($complianceRate, 1) }}% 
            @if($complianceRate >= 90)
                <span style="color: #22543d;">(Excelente)</span>
            @elseif($complianceRate >= 80)
                <span style="color: #7b341e;">(Bueno)</span>
            @elseif($complianceRate >= 60)
                <span style="color: #d69e2e;">(Regular)</span>
            @else
                <span style="color: #742a2a;">(Requiere Mejora)</span>
            @endif
        </p>
        <p style="margin: 5px 0; color: #4a5568;">
            • <strong>Documentos Críticos:</strong> {{ number_format($overdueDocuments) }} documentos vencidos requieren atención inmediata.
        </p>
        <p style="margin: 5px 0; color: #4a5568;">
            • <strong>Documentos Sin SLA:</strong> {{ number_format($noSlaDocuments) }} documentos no tienen fecha de vencimiento definida.
        </p>
        @if($overdueDocuments > 0)
            <p style="margin: 5px 0; color: #742a2a; font-weight: bold;">
                ⚠️ <strong>Recomendación:</strong> Revisar y reasignar los documentos vencidos para mejorar el cumplimiento SLA.
            </p>
        @endif
    </div>
@endsection