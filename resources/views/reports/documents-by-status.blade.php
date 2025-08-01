@extends('reports.base')

@section('content')
    @php
        $totalDocuments = $data->sum('total');
        $statusGroups = $data->groupBy('status.name');
    @endphp

    <div class="summary">
        <div class="summary-card">
            <h4>Total Documentos</h4>
            <div class="number">{{ number_format($totalDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Estados Diferentes</h4>
            <div class="number">{{ $statusGroups->count() }}</div>
        </div>
        <div class="summary-card">
            <h4>Promedio por Estado</h4>
            <div class="number">{{ $statusGroups->count() > 0 ? number_format($totalDocuments / $statusGroups->count(), 1) : 0 }}</div>
        </div>
        <div class="summary-card">
            <h4>Período</h4>
            <div class="number text-xs">
                @if(isset($filters['date_from']) && isset($filters['date_to']))
                    {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }} - 
                    {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}
                @else
                    Todos los registros
                @endif
            </div>
        </div>
    </div>

    <h2>Distribución de Documentos por Estado</h2>
    
    <table>
        <thead>
            <tr>
                <th>Estado</th>
                <th>Cantidad</th>
                <th>Porcentaje</th>
                <th>Documentos Recientes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($statusGroups as $statusName => $documents)
                @php
                    $statusTotal = $documents->sum('total');
                    $percentage = $totalDocuments > 0 ? ($statusTotal / $totalDocuments) * 100 : 0;
                @endphp
                <tr>
                    <td>
                        <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $statusName)) }}">
                            {{ $statusName }}
                        </span>
                    </td>
                    <td class="text-center font-bold">{{ number_format($statusTotal) }}</td>
                    <td class="text-center">{{ number_format($percentage, 1) }}%</td>
                    <td class="text-sm">
                        @foreach($documents->take(3) as $doc)
                            <div>• {{ Str::limit($doc->title, 40) }}</div>
                        @endforeach
                        @if($documents->count() > 3)
                            <div class="text-xs">... y {{ $documents->count() - 3 }} más</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($data->isNotEmpty())
        <div class="page-break"></div>
        <h2>Detalle de Documentos</h2>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Categoría</th>
                    <th>Usuario</th>
                    <th>Departamento</th>
                    <th>Fecha Creación</th>
                    <th>Fecha Vencimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $document)
                    <tr>
                        <td class="text-center">{{ $document->id }}</td>
                        <td>{{ Str::limit($document->title, 30) }}</td>
                        <td>
                            <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $document->status->name ?? 'unknown')) }}">
                                {{ $document->status->name ?? 'Sin estado' }}
                            </span>
                        </td>
                        <td>{{ $document->category->name ?? 'Sin categoría' }}</td>
                        <td>{{ $document->user->name ?? 'Sin asignar' }}</td>
                        <td>{{ $document->department->name ?? 'Sin departamento' }}</td>
                        <td class="text-center">{{ $document->created_at->format('d/m/Y') }}</td>
                        <td class="text-center">
                            @if($document->due_date)
                                {{ $document->due_date->format('d/m/Y') }}
                                @if($document->due_date->isPast() && $document->status->name !== 'Completed')
                                    <span class="status-badge status-overdue">VENCIDO</span>
                                @endif
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
        <h3 style="margin: 0 0 10px 0; color: #2d3748;">Resumen Ejecutivo</h3>
        <p style="margin: 5px 0; color: #4a5568;">
            • Se encontraron <strong>{{ number_format($totalDocuments) }}</strong> documentos distribuidos en <strong>{{ $statusGroups->count() }}</strong> estados diferentes.
        </p>
        @if($statusGroups->isNotEmpty())
            @php
                $mostCommonStatus = $statusGroups->sortByDesc(function($docs) { return $docs->sum('total'); })->first();
                $mostCommonStatusName = $statusGroups->sortByDesc(function($docs) { return $docs->sum('total'); })->keys()->first();
            @endphp
            <p style="margin: 5px 0; color: #4a5568;">
                • El estado más común es <strong>"{{ $mostCommonStatusName }}"</strong> con {{ number_format($mostCommonStatus->sum('total')) }} documentos 
                ({{ number_format(($mostCommonStatus->sum('total') / $totalDocuments) * 100, 1) }}% del total).
            </p>
        @endif
        <p style="margin: 5px 0; color: #4a5568;">
            • Reporte generado el {{ $generated_at->format('d/m/Y H:i:s') }} 
            @if(isset($filters['date_from']) || isset($filters['date_to']))
                con filtros de fecha aplicados.
            @else
                incluyendo todos los registros históricos.
            @endif
        </p>
    </div>
@endsection