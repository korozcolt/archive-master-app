@extends('reports.base')

@section('content')
    @php
        $totalDocuments = $data->count();
        $overdueDocuments = $data->filter(fn ($document) => ($document->sla_status?->value ?? null) === 'overdue')->count();
        $warningDocuments = $data->filter(fn ($document) => ($document->sla_status?->value ?? null) === 'warning')->count();
        $frozenDocuments = $data->filter(fn ($document) => ($document->sla_status?->value ?? null) === 'frozen')->count();
    @endphp

    <div class="summary">
        <div class="summary-card">
            <h4>Total PQRS</h4>
            <div class="number">{{ number_format($totalDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Por vencer</h4>
            <div class="number" style="color: #b7791f;">{{ number_format($warningDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Vencidos</h4>
            <div class="number" style="color: #742a2a;">{{ number_format($overdueDocuments) }}</div>
        </div>
        <div class="summary-card">
            <h4>Congelados</h4>
            <div class="number" style="color: #2b6cb0;">{{ number_format($frozenDocuments) }}</div>
        </div>
    </div>

    <h2>Seguimiento legal y cumplimiento SLA</h2>

    <table>
        <thead>
            <tr>
                <th>Radicado</th>
                <th>Título</th>
                <th>Tipo PQRS</th>
                <th>Base legal</th>
                <th>Estado SLA</th>
                <th>Fecha límite</th>
                <th>Asignado a</th>
                <th>Departamento</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->sortBy('due_date') as $document)
                <tr>
                    <td>{{ $document->document_number }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($document->title, 40) }}</td>
                    <td>{{ $document->pqrs_type ?? 'Sin clasificar' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($document->legal_basis ?? 'Sin base legal', 40) }}</td>
                    <td>{{ $document->sla_status_label ?? 'Sin SLA' }}</td>
                    <td>{{ $document->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                    <td>{{ $document->assignee?->name ?? 'Sin asignar' }}</td>
                    <td>{{ $document->department?->name ?? 'Sin departamento' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
