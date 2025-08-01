<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CustomReportExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithTitle, 
    WithCustomStartCell,
    WithEvents
{
    protected Collection $data;
    protected string $reportType;
    protected string $reportName;
    protected array $config;
    protected array $aggregates;

    public function __construct(
        Collection $data, 
        string $reportType, 
        string $reportName, 
        array $config = [],
        array $aggregates = []
    ) {
        $this->data = $data;
        $this->reportType = $reportType;
        $this->reportName = $reportName;
        $this->config = $config;
        $this->aggregates = $aggregates;
    }

    /**
     * Return the data collection
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return match ($this->reportType) {
            'documents' => [
                'ID',
                'Título',
                'Estado',
                'Categoría',
                'Departamento',
                'Usuario Creador',
                'Fecha Creación',
                'Última Actualización',
                'Días de Procesamiento',
                'Prioridad',
                'Descripción'
            ],
            'users' => [
                'ID',
                'Nombre',
                'Email',
                'Departamento',
                'Estado',
                'Total Documentos',
                'Documentos Activos',
                'Fecha Registro',
                'Último Acceso',
                'Rol'
            ],
            'departments' => [
                'ID',
                'Nombre',
                'Descripción',
                'Total Usuarios',
                'Usuarios Activos',
                'Total Documentos',
                'Documentos Pendientes',
                'Fecha Creación',
                'Responsable'
            ],
            default => [
                'ID',
                'Nombre',
                'Descripción',
                'Fecha Creación',
                'Estado'
            ]
        };
    }

    /**
     * Map each row of data
     */
    public function map($item): array
    {
        return match ($this->reportType) {
            'documents' => $this->mapDocument($item),
            'users' => $this->mapUser($item),
            'departments' => $this->mapDepartment($item),
            default => $this->mapGeneric($item)
        };
    }

    /**
     * Map document data
     */
    private function mapDocument($document): array
    {
        $processingDays = $document->created_at && $document->updated_at 
            ? $document->created_at->diffInDays($document->updated_at)
            : 0;

        return [
            $document->id,
            $document->title ?? 'Sin título',
            $document->status->name ?? 'Sin estado',
            $document->category->name ?? 'Sin categoría',
            $document->department->name ?? 'Sin departamento',
            $document->user->name ?? 'Sin usuario',
            $document->created_at ? $document->created_at->format('d/m/Y H:i') : 'N/A',
            $document->updated_at ? $document->updated_at->format('d/m/Y H:i') : 'N/A',
            $processingDays,
            $document->priority ?? 'Normal',
            $document->description ? substr($document->description, 0, 100) . '...' : 'Sin descripción'
        ];
    }

    /**
     * Map user data
     */
    private function mapUser($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->department->name ?? 'Sin departamento',
            $user->is_active ? 'Activo' : 'Inactivo',
            $user->documents_count ?? $user->documents->count(),
            $user->documents()->whereHas('status', function($q) {
                $q->whereIn('name', ['Pendiente', 'En Proceso']);
            })->count(),
            $user->created_at ? $user->created_at->format('d/m/Y H:i') : 'N/A',
            $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca',
            $user->roles->pluck('name')->implode(', ') ?: 'Sin rol'
        ];
    }

    /**
     * Map department data
     */
    private function mapDepartment($department): array
    {
        return [
            $department->id,
            $department->name,
            $department->description ?? 'Sin descripción',
            $department->users_count ?? $department->users->count(),
            $department->users()->where('is_active', true)->count(),
            $department->documents_count ?? $department->documents->count(),
            $department->documents()->whereHas('status', function($q) {
                $q->whereIn('name', ['Pendiente', 'En Proceso']);
            })->count(),
            $department->created_at ? $department->created_at->format('d/m/Y H:i') : 'N/A',
            $department->manager->name ?? 'Sin responsable'
        ];
    }

    /**
     * Map generic data
     */
    private function mapGeneric($item): array
    {
        return [
            $item->id ?? 'N/A',
            $item->name ?? $item->title ?? 'Sin nombre',
            $item->description ?? 'Sin descripción',
            $item->created_at ? $item->created_at->format('d/m/Y H:i') : 'N/A',
            $item->status ?? 'Sin estado'
        ];
    }

    /**
     * Define styles for the worksheet
     */
    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '007BFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
            // Data rows styling
            'A:Z' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]
        ];
    }

    /**
     * Set the title of the worksheet
     */
    public function title(): string
    {
        return substr($this->reportName, 0, 31); // Excel sheet name limit
    }

    /**
     * Set the starting cell for data
     */
    public function startCell(): string
    {
        return 'A6'; // Leave space for report header
    }

    /**
     * Register events for additional formatting
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $this->addReportHeader($event->sheet->getDelegate());
                $this->addSummarySection($event->sheet->getDelegate());
                $this->autoSizeColumns($event->sheet->getDelegate());
                $this->addFooter($event->sheet->getDelegate());
            },
        ];
    }

    /**
     * Add report header information
     */
    private function addReportHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        // Report title
        $sheet->setCellValue('A1', $this->reportName);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '007BFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Report info
        $sheet->setCellValue('A2', 'Sistema de Gestión Documental');
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $sheet->setCellValue('A3', 'Generado el: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Report details
        $dateRange = 'Período: ';
        if (isset($this->config['date_from']) && isset($this->config['date_to'])) {
            $dateRange .= Carbon::parse($this->config['date_from'])->format('d/m/Y') . 
                         ' - ' . Carbon::parse($this->config['date_to'])->format('d/m/Y');
        } else {
            $dateRange .= 'Todos los registros';
        }
        
        $sheet->setCellValue('A4', $dateRange);
        $sheet->setCellValue('F4', 'Total de registros: ' . $this->data->count());
        $sheet->setCellValue('I4', 'Generado por: ' . (Auth::check() ? Auth::user()->name : 'Sistema'));
    }

    /**
     * Add summary section with aggregates
     */
    private function addSummarySection(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        if (empty($this->aggregates)) {
            return;
        }

        $lastRow = $sheet->getHighestRow() + 2;
        
        $sheet->setCellValue('A' . $lastRow, 'RESUMEN EJECUTIVO');
        $sheet->mergeCells('A' . $lastRow . ':K' . $lastRow);
        $sheet->getStyle('A' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '007BFF']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F8F9FA']
            ]
        ]);

        $row = $lastRow + 2;
        $col = 0;
        
        foreach ($this->aggregates as $key => $value) {
            if ($col >= 4) {
                $col = 0;
                $row += 2;
            }
            
            $cellCol = chr(65 + ($col * 3)); // A, D, G, J
            
            $label = str_replace('_', ' ', ucwords($key));
            $displayValue = is_numeric($value) ? number_format($value, 0, ',', '.') : 
                           (is_array($value) ? count($value) : $value);
            
            $sheet->setCellValue($cellCol . $row, $label);
            $sheet->setCellValue($cellCol . ($row + 1), $displayValue);
            
            // Style the metric
            $sheet->getStyle($cellCol . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            
            $sheet->getStyle($cellCol . ($row + 1))->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '007BFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            
            $col++;
        }
    }

    /**
     * Auto-size columns for better readability
     */
    private function autoSizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Add footer information
     */
    private function addFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow() + 3;
        
        $sheet->setCellValue('A' . $lastRow, 'Este reporte fue generado automáticamente por el Sistema de Gestión Documental.');
        $sheet->mergeCells('A' . $lastRow . ':K' . $lastRow);
        $sheet->getStyle('A' . $lastRow)->applyFromArray([
            'font' => ['size' => 9, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        $sheet->setCellValue('A' . ($lastRow + 1), 'Para más información, contacte al administrador del sistema.');
        $sheet->mergeCells('A' . ($lastRow + 1) . ':K' . ($lastRow + 1));
        $sheet->getStyle('A' . ($lastRow + 1))->applyFromArray([
            'font' => ['size' => 9, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
    }
}