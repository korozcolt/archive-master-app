@extends('reports.base')

@section('content')
    @php
        $totalDocuments = $data->count();
        $missingClassification = $data->filter(fn ($document) => ! $document->archive_classification_complete)->count();
        $managementDocuments = $data->filter(fn ($document) => ($document->archive_phase?->value ?? null) === 'gestion')->count();
        $centralDocuments = $data->filter(fn ($document) => ($document->archive_phase?->value ?? null) === 'central')->count();
    @endphp

    <div class="summary">
        <div class="summary-card">
            <h4>Total archivístico</h4>
            <div class="number">{{ number_format($totalDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Gestión</h4>
            <div class="number">{{ number_format($managementDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Central</h4>
            <div class="number">{{ number_format($centralDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Incompletos</h4>
            <div class="number" style="color: #742a2a;">{{ number_format($missingClassification) }}</div>
        </div>
    </div>

    <h2>Clasificación archivística y retención</h2>

    <table>
        <thead>
            <tr>
                <th>Radicado</th>
                <th>Título</th>
                <th>Fase</th>
                <th>Clasificación</th>
                <th>Nivel de acceso</th>
                <th>Retención gestión</th>
                <th>Retención central</th>
                <th>Disposición final</th>
                <th>Ubicación física</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->sortBy('archive_classification_code') as $document)
                <tr>
                    <td>{{ $document->document_number }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($document->title, 40) }}</td>
                    <td>{{ $document->archive_phase?->value ?? 'Sin fase' }}</td>
                    <td>{{ $document->archive_classification_code ?? 'Sin clasificación' }}</td>
                    <td>{{ $document->access_level?->value ?? 'Sin acceso' }}</td>
                    <td>{{ $document->retention_management_years ?? 'N/A' }}</td>
                    <td>{{ $document->retention_central_years ?? 'N/A' }}</td>
                    <td>{{ $document->final_disposition?->value ?? 'Sin disposición' }}</td>
                    <td>{{ $document->physicalLocation?->full_path ?? 'Sin ubicación' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
