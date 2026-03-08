<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $data;

    protected string $reportType;

    public function __construct(Collection $data, string $reportType)
    {
        $this->data = $data;
        $this->reportType = $reportType;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return match ($this->reportType) {
            'documents-by-status' => [
                'ID',
                'Título',
                'Estado',
                'Categoría',
                'Usuario',
                'Departamento',
                'Fecha Creación',
                'Fecha Vencimiento',
                'Total por Estado',
            ],
            'sla-compliance' => [
                'ID',
                'Título',
                'Estado',
                'Estado SLA',
                'Categoría',
                'Usuario',
                'Departamento',
                'Fecha Creación',
                'Fecha Vencimiento',
            ],
            'legal-sla-governance' => [
                'ID',
                'Radicado',
                'Título',
                'Tipo PQRS',
                'Base legal',
                'Estado SLA',
                'Fecha límite legal',
                'Asignado a',
                'Departamento',
                'Archivado',
            ],
            'archive-governance' => [
                'ID',
                'Radicado',
                'Título',
                'Fase de archivo',
                'Clasificación',
                'Nivel de acceso',
                'Retención gestión',
                'Retención central',
                'Disposición final',
                'Ubicación física',
            ],
            'user-activity' => [
                'ID Usuario',
                'Nombre',
                'Email',
                'Departamento',
                'Total Documentos',
                'Documentos Completados',
                'Documentos Pendientes',
                'Porcentaje Completado',
            ],
            'documents-by-department' => [
                'ID Departamento',
                'Nombre Departamento',
                'Total Documentos',
                'Documentos Completados',
                'Documentos Pendientes',
                'Porcentaje Completado',
            ],
            default => [
                'ID',
                'Título',
                'Estado',
                'Fecha Creación',
            ]
        };
    }

    /**
     * @param  mixed  $row
     */
    public function map($row): array
    {
        return match ($this->reportType) {
            'documents-by-status' => [
                $row->id,
                $row->title,
                $row->status?->name ?? 'N/A',
                $row->category?->name ?? 'N/A',
                $row->user?->name ?? 'N/A',
                $row->department?->name ?? 'N/A',
                $row->created_at?->format('d/m/Y H:i'),
                $row->due_date?->format('d/m/Y') ?? 'Sin fecha',
                $row->total ?? 0,
            ],
            'sla-compliance' => [
                $row->id,
                $row->title,
                $row->status?->name ?? 'N/A',
                $row->sla_status_label ?? $row->sla_status?->value ?? 'N/A',
                $row->category?->name ?? 'N/A',
                $row->assignee?->name ?? $row->creator?->name ?? 'N/A',
                $row->department?->name ?? 'N/A',
                $row->created_at?->format('d/m/Y H:i'),
                $row->due_date?->format('d/m/Y') ?? 'Sin fecha',
            ],
            'legal-sla-governance' => [
                $row->id,
                $row->document_number,
                $row->title,
                $row->pqrs_type ?? 'Sin clasificar',
                $row->legal_basis ?? 'Sin base legal',
                $row->sla_status_label ?? $row->sla_status?->value ?? 'Sin SLA',
                $row->due_date?->format('d/m/Y') ?? 'Sin fecha',
                $row->assignee?->name ?? 'Sin asignar',
                $row->department?->name ?? 'Sin departamento',
                $row->is_archived ? 'Sí' : 'No',
            ],
            'archive-governance' => [
                $row->id,
                $row->document_number,
                $row->title,
                $row->archive_phase?->value ?? 'Sin fase',
                $row->archive_classification_code ?? 'Sin clasificación',
                $row->access_level?->value ?? 'Sin acceso',
                $row->retention_management_years ?? 'N/A',
                $row->retention_central_years ?? 'N/A',
                $row->final_disposition?->value ?? 'Sin disposición',
                $row->physicalLocation?->full_path ?? 'Sin ubicación',
            ],
            'user-activity' => [
                $row->id,
                $row->name,
                $row->email,
                $row->department?->name ?? 'N/A',
                $row->documents_count ?? 0,
                $row->completed_documents_count ?? 0,
                $row->pending_documents_count ?? 0,
                $row->documents_count > 0
                    ? round(($row->completed_documents_count / $row->documents_count) * 100, 2).'%'
                    : '0%',
            ],
            'documents-by-department' => [
                $row->id,
                $row->name,
                $row->documents_count ?? 0,
                $row->documents->where('status.name', 'Completed')->count(),
                $row->documents->where('status.name', '!=', 'Completed')->count(),
                $row->documents_count > 0
                    ? round(($row->documents->where('status.name', 'Completed')->count() / $row->documents_count) * 100, 2).'%'
                    : '0%',
            ],
            default => [
                $row->id,
                $row->title ?? $row->name,
                $row->status?->name ?? 'N/A',
                $row->created_at?->format('d/m/Y H:i'),
            ]
        };
    }

    /**
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            // Auto-size columns
            'A:Z' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ],
        ];
    }

    public function title(): string
    {
        return match ($this->reportType) {
            'documents-by-status' => 'Documentos por Estado',
            'sla-compliance' => 'Cumplimiento SLA',
            'legal-sla-governance' => 'SLA Legal',
            'archive-governance' => 'Gobernanza Archivo',
            'user-activity' => 'Actividad Usuarios',
            'documents-by-department' => 'Documentos por Depto',
            default => 'Reporte'
        };
    }
}
