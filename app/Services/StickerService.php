<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PhysicalLocation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class StickerService
{
    protected BarcodeService $barcodeService;

    protected QRCodeService $qrCodeService;

    public function __construct(BarcodeService $barcodeService, QRCodeService $qrCodeService)
    {
        $this->barcodeService = $barcodeService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Generar sticker para un documento
     */
    public function generateForDocument(
        Document $document,
        string $template = 'standard',
        array $options = []
    ): array {
        $documentUrl = route('documents.show', $document);
        $barcode = $this->barcodeService->getAsDataUrl(
            $documentUrl,
            $options['barcode_width'] ?? 2,
            $options['barcode_height'] ?? 50
        );

        $qrCode = $this->qrCodeService->getAsDataUrl(
            $documentUrl,
            $options['qr_size'] ?? 200,
            "DOC: {$document->document_number}"
        );

        $data = [
            'type' => 'document',
            'document' => $document,
            'barcode' => $barcode,
            'qrcode' => $qrCode,
            'title' => $document->title,
            'document_number' => $document->document_number,
            'document_url' => $documentUrl,
            'company' => $this->getCompanyName($document->company),
            'location' => $document->getCurrentLocationPath() ?? 'Sin ubicación asignada',
            'created_at' => $document->created_at?->format('d/m/Y'),
            'options' => $options,
        ];

        return $data;
    }

    /**
     * Generar sticker para una ubicación física
     */
    public function generateForLocation(
        PhysicalLocation $location,
        string $template = 'standard',
        array $options = []
    ): array {
        $barcode = $this->barcodeService->getAsDataUrl(
            $location->code,
            $options['barcode_width'] ?? 2,
            $options['barcode_height'] ?? 50
        );

        // Generar datos del QR para la ubicación
        $qrData = json_encode([
            'type' => 'location',
            'id' => $location->id,
            'code' => $location->code,
            'full_path' => $location->full_path,
            'qr_code' => $location->qr_code,
            'capacity_total' => $location->capacity_total,
            'capacity_used' => $location->capacity_used,
        ]);

        $qrCode = $this->qrCodeService->getAsDataUrl(
            $qrData,
            $options['qr_size'] ?? 200,
            $location->code
        );

        $data = [
            'type' => 'location',
            'location' => $location,
            'barcode' => $barcode,
            'qrcode' => $qrCode,
            'code' => $location->code,
            'full_path' => $location->full_path,
            'capacity' => "{$location->capacity_used}/{$location->capacity_total}",
            'capacity_percentage' => $location->getCapacityPercentage(),
            'company' => $this->getCompanyName($location->company),
            'options' => $options,
        ];

        return $data;
    }

    /**
     * Generar PDF de sticker para documento
     */
    public function generatePDFForDocument(
        Document $document,
        string $template = 'standard',
        array $options = []
    ): string {
        $data = $this->generateForDocument($document, $template, $options);

        $pdf = Pdf::loadView("stickers.document.{$template}", $data);

        // Configurar tamaño de página según template
        $pageSize = $this->getPageSizeForTemplate($template);
        $pdf->setPaper($pageSize['width'].'mm', $pageSize['height'].'mm');

        return $pdf->output();
    }

    /**
     * Generar PDF de sticker para ubicación
     */
    public function generatePDFForLocation(
        PhysicalLocation $location,
        string $template = 'standard',
        array $options = []
    ): string {
        $data = $this->generateForLocation($location, $template, $options);

        $pdf = Pdf::loadView("stickers.location.{$template}", $data);

        // Configurar tamaño de página según template
        $pageSize = $this->getPageSizeForTemplate($template);
        $pdf->setPaper($pageSize['width'].'mm', $pageSize['height'].'mm');

        return $pdf->output();
    }

    /**
     * Generar stickers en batch para múltiples documentos
     */
    public function generateBatchForDocuments(
        array $documents,
        string $template = 'standard',
        array $options = []
    ): string {
        $stickers = [];

        foreach ($documents as $document) {
            if ($document instanceof Document) {
                $stickers[] = $this->generateForDocument($document, $template, $options);
            }
        }

        $pdf = Pdf::loadView("stickers.batch.{$template}", [
            'stickers' => $stickers,
            'options' => $options,
        ]);

        // Usar tamaño A4 para lotes
        $pdf->setPaper('a4', $options['orientation'] ?? 'portrait');

        return $pdf->output();
    }

    /**
     * Generar stickers en batch para múltiples ubicaciones
     */
    public function generateBatchForLocations(
        array $locations,
        string $template = 'standard',
        array $options = []
    ): string {
        $stickers = [];

        foreach ($locations as $location) {
            if ($location instanceof PhysicalLocation) {
                $stickers[] = $this->generateForLocation($location, $template, $options);
            }
        }

        $pdf = Pdf::loadView("stickers.batch.{$template}", [
            'stickers' => $stickers,
            'type' => 'location',
            'options' => $options,
        ]);

        // Usar tamaño A4 para lotes
        $pdf->setPaper('a4', $options['orientation'] ?? 'portrait');

        return $pdf->output();
    }

    /**
     * Guardar sticker como archivo
     */
    public function saveSticker(
        string $pdfContent,
        string $filename,
        string $disk = 'public'
    ): string {
        $path = "stickers/{$filename}.pdf";
        Storage::disk($disk)->put($path, $pdfContent);

        return $path;
    }

    /**
     * Generar vista previa HTML del sticker
     */
    public function previewDocument(
        Document $document,
        string $template = 'standard',
        array $options = []
    ): string {
        $data = $this->generateForDocument($document, $template, $options);

        return View::make("stickers.document.{$template}", $data)->render();
    }

    /**
     * Generar vista previa HTML del sticker de ubicación
     */
    public function previewLocation(
        PhysicalLocation $location,
        string $template = 'standard',
        array $options = []
    ): string {
        $data = $this->generateForLocation($location, $template, $options);

        return View::make("stickers.location.{$template}", $data)->render();
    }

    /**
     * Obtener templates disponibles
     */
    public function getAvailableTemplates(): array
    {
        return [
            'standard' => [
                'name' => 'Estándar',
                'description' => 'Sticker estándar con QR y barcode',
                'size' => '50mm x 80mm',
                'supports' => ['document', 'location'],
            ],
            'compact' => [
                'name' => 'Compacto',
                'description' => 'Sticker pequeño solo con QR',
                'size' => '40mm x 40mm',
                'supports' => ['document', 'location'],
            ],
            'detailed' => [
                'name' => 'Detallado',
                'description' => 'Sticker grande con información completa',
                'size' => '80mm x 100mm',
                'supports' => ['document', 'location'],
            ],
            'label' => [
                'name' => 'Etiqueta',
                'description' => 'Formato de etiqueta rectangular',
                'size' => '100mm x 50mm',
                'supports' => ['document', 'location'],
            ],
        ];
    }

    /**
     * Obtener tamaño de página según template
     */
    protected function getPageSizeForTemplate(string $template): array
    {
        return match ($template) {
            'compact' => ['width' => 40, 'height' => 40],
            'standard' => ['width' => 50, 'height' => 80],
            'detailed' => ['width' => 80, 'height' => 100],
            'label' => ['width' => 100, 'height' => 50],
            default => ['width' => 50, 'height' => 80],
        };
    }

    /**
     * Obtener nombre de la compañía
     */
    protected function getCompanyName($company): string
    {
        if (! $company) {
            return 'Sin compañía';
        }

        $name = $company->name;

        if (is_array($name)) {
            return $company->getTranslation('name', app()->getLocale());
        }

        return $name;
    }

    /**
     * Obtener opciones por defecto para stickers
     */
    public function getDefaultOptions(): array
    {
        return [
            'barcode_width' => 2,
            'barcode_height' => 50,
            'qr_size' => 200,
            'include_logo' => false,
            'include_date' => true,
            'include_company' => true,
            'orientation' => 'portrait',
            'copies' => 1,
        ];
    }

    /**
     * Validar opciones de sticker
     */
    public function validateOptions(array $options): bool
    {
        $validKeys = array_keys($this->getDefaultOptions());

        foreach (array_keys($options) as $key) {
            if (! in_array($key, $validKeys)) {
                return false;
            }
        }

        return true;
    }
}
