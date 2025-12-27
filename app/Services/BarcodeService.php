<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PhysicalLocation;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Illuminate\Support\Facades\Storage;
use Exception;

class BarcodeService
{
    protected BarcodeGeneratorPNG $generatorPNG;
    protected BarcodeGeneratorSVG $generatorSVG;
    protected BarcodeGeneratorHTML $generatorHTML;

    public function __construct()
    {
        $this->generatorPNG = new BarcodeGeneratorPNG();
        $this->generatorSVG = new BarcodeGeneratorSVG();
        $this->generatorHTML = new BarcodeGeneratorHTML();
    }

    /**
     * Generar barcode para un documento
     */
    public function generateForDocument(
        Document $document,
        string $format = 'png',
        int $widthFactor = 2,
        int $height = 50
    ): string {
        if (empty($document->barcode)) {
            throw new Exception('El documento no tiene un código de barras asignado');
        }

        return $this->generate(
            $document->barcode,
            $format,
            $widthFactor,
            $height
        );
    }

    /**
     * Generar barcode para una ubicación física
     */
    public function generateForLocation(
        PhysicalLocation $location,
        string $format = 'png',
        int $widthFactor = 2,
        int $height = 50
    ): string {
        if (empty($location->code)) {
            throw new Exception('La ubicación no tiene un código asignado');
        }

        return $this->generate(
            $location->code,
            $format,
            $widthFactor,
            $height
        );
    }

    /**
     * Generar barcode genérico
     */
    public function generate(
        string $code,
        string $format = 'png',
        int $widthFactor = 2,
        int $height = 50,
        string $type = 'TYPE_CODE_128'
    ): string {
        $barcodeType = constant('Picqer\Barcode\BarcodeGenerator::' . $type);

        return match ($format) {
            'svg' => $this->generatorSVG->getBarcode($code, $barcodeType, $widthFactor, $height),
            'html' => $this->generatorHTML->getBarcode($code, $barcodeType, $widthFactor, $height),
            'png', 'base64' => base64_encode(
                $this->generatorPNG->getBarcode($code, $barcodeType, $widthFactor, $height)
            ),
            default => throw new Exception("Formato de barcode no soportado: {$format}"),
        };
    }

    /**
     * Generar y guardar barcode como archivo
     */
    public function generateAndSave(
        string $code,
        string $filename,
        string $disk = 'public',
        int $widthFactor = 2,
        int $height = 50
    ): string {
        $barcodePNG = $this->generatorPNG->getBarcode(
            $code,
            BarcodeGeneratorPNG::TYPE_CODE_128,
            $widthFactor,
            $height
        );

        $path = "barcodes/{$filename}.png";
        Storage::disk($disk)->put($path, $barcodePNG);

        return $path;
    }

    /**
     * Obtener barcode como data URL para embeber en HTML
     */
    public function getAsDataUrl(
        string $code,
        int $widthFactor = 2,
        int $height = 50
    ): string {
        $barcodePNG = $this->generatorPNG->getBarcode(
            $code,
            BarcodeGeneratorPNG::TYPE_CODE_128,
            $widthFactor,
            $height
        );

        return 'data:image/png;base64,' . base64_encode($barcodePNG);
    }

    /**
     * Generar múltiples barcodes para documentos
     */
    public function generateBatch(
        array $documents,
        string $format = 'png'
    ): array {
        $barcodes = [];

        foreach ($documents as $document) {
            if ($document instanceof Document && !empty($document->barcode)) {
                $barcodes[$document->id] = $this->generateForDocument($document, $format);
            }
        }

        return $barcodes;
    }

    /**
     * Validar código de barcode
     */
    public function validateCode(string $code): bool
    {
        // Validar longitud mínima
        if (strlen($code) < 3) {
            return false;
        }

        // Validar caracteres permitidos (alfanuméricos, guiones, espacios)
        if (!preg_match('/^[A-Za-z0-9\-\s]+$/', $code)) {
            return false;
        }

        return true;
    }

    /**
     * Obtener tipos de barcode disponibles
     */
    public function getAvailableTypes(): array
    {
        return [
            'TYPE_CODE_128' => 'Code 128 (Recomendado)',
            'TYPE_CODE_39' => 'Code 39',
            'TYPE_EAN_13' => 'EAN-13',
            'TYPE_UPC_A' => 'UPC-A',
            'TYPE_CODE_93' => 'Code 93',
            'TYPE_STANDARD_2_5' => 'Standard 2 of 5',
            'TYPE_ITF_14' => 'ITF-14',
        ];
    }

    /**
     * Obtener formatos de salida disponibles
     */
    public function getAvailableFormats(): array
    {
        return [
            'png' => 'PNG (Imagen)',
            'svg' => 'SVG (Vector)',
            'html' => 'HTML',
            'base64' => 'Base64',
        ];
    }
}
