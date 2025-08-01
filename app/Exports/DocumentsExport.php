<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

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

    /**
     * @return array
     */
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
                'Total por Estado'
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
                'Fecha Vencimiento'
            ],
            'user-activity' => [
                'ID Usuario',
                'Nombre',
                'Email',
                'Departamento',
                'Total Documentos',
                'Documentos Completados',
                'Documentos Pendientes',
                'Porcentaje Completado'
            ],
            'documents-by-department' => [
                'ID Departamento',
                'Nombre Departamento',
                'Total Documentos',
                'Documentos Completados',
                'Documentos Pendientes',
                'Porcentaje Completado'
            ],
            default => [
                'ID',
                'Título',
                'Estado',
                'Fecha Creación'
            ]
        };
    }

    /**
     * @param mixed $row
     * @return array
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
                $row->total ?? 0
            ],
            'sla-compliance' => [
                $row->id,
                $row->title,
                $row->status?->name ?? 'N/A',
                $row->sla_status ?? 'N/A',
                $row->category?->name ?? 'N/A',
                $row->user?->name ?? 'N/A',
                $row->department?->name ?? 'N/A',
                $row->created_at?->format('d/m/Y H:i'),
                $row->due_date?->format('d/m/Y') ?? 'Sin fecha'
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
                    ? round(($row->completed_documents_count / $row->documents_count) * 100, 2) . '%'
                    : '0%'
            ],
            'documents-by-department' => [
                $row->id,
                $row->name,
                $row->documents_count ?? 0,
                $row->documents->where('status.name', 'Completed')->count(),
                $row->documents->where('status.name', '!=', 'Completed')->count(),
                $row->documents_count > 0 
                    ? round(($row->documents->where('status.name', 'Completed')->count() / $row->documents_count) * 100, 2) . '%'
                    : '0%'
            ],
            default => [
                $row->id,
                $row->title ?? $row->name,
                $row->status?->name ?? 'N/A',
                $row->created_at?->format('d/m/Y H:i')
            ]
        };
    }

    /**
     * @param Worksheet $sheet
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
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
            // Auto-size columns
            'A:Z' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ]
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return match ($this->reportType) {
            'documents-by-status' => 'Documentos por Estado',
            'sla-compliance' => 'Cumplimiento SLA',
            'user-activity' => 'Actividad Usuarios',
            'documents-by-department' => 'Documentos por Depto',
            default => 'Reporte'
        };
    }
}