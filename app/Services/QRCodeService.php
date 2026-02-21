<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PhysicalLocation;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Exception;
use Illuminate\Support\Facades\Storage;

class QRCodeService
{
    /**
     * Generar QR code para un documento
     */
    public function generateForDocument(
        Document $document,
        string $format = 'png',
        int $size = 300,
        ?string $label = null
    ): string {
        $data = $this->getDocumentQRData($document);

        return $this->generate(
            $data,
            $format,
            $size,
            $label ?? "DOC: {$document->document_number}"
        );
    }

    /**
     * Generar QR code para tracking público
     */
    public function generateForTracking(
        Document $document,
        string $format = 'png',
        int $size = 300
    ): string {
        if (empty($document->public_tracking_code)) {
            throw new Exception('El documento no tiene un código de tracking público');
        }

        $trackingUrl = route('tracking.show', ['code' => $document->public_tracking_code]);

        return $this->generate(
            $trackingUrl,
            $format,
            $size,
            "Tracking: {$document->document_number}"
        );
    }

    /**
     * Generar QR code para una ubicación física
     */
    public function generateForLocation(
        PhysicalLocation $location,
        string $format = 'png',
        int $size = 300,
        ?string $label = null
    ): string {
        $data = $this->getLocationQRData($location);

        return $this->generate(
            $data,
            $format,
            $size,
            $label ?? $location->code
        );
    }

    /**
     * Generar QR code genérico
     */
    public function generate(
        string $data,
        string $format = 'png',
        int $size = 300,
        ?string $label = null,
        string $errorCorrectionLevel = 'high'
    ): string {
        $writer = $format === 'svg' ? new SvgWriter : new PngWriter;

        $errorLevel = match ($errorCorrectionLevel) {
            'low' => ErrorCorrectionLevel::Low,
            'medium' => ErrorCorrectionLevel::Medium,
            'quartile' => ErrorCorrectionLevel::Quartile,
            'high' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::High,
        };

        $result = (new Builder(
            writer: $writer,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $errorLevel,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            labelText: $label ?? '',
            labelAlignment: LabelAlignment::Center,
        ))->build();

        if ($format === 'base64' || $format === 'png') {
            return base64_encode($result->getString());
        }

        return $result->getString();
    }

    /**
     * Generar y guardar QR code como archivo
     */
    public function generateAndSave(
        string $data,
        string $filename,
        string $disk = 'public',
        int $size = 300,
        ?string $label = null
    ): string {
        $qrCode = (new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            labelText: $label ?? '',
            labelAlignment: LabelAlignment::Center,
        ))->build();

        $path = "qrcodes/{$filename}.png";
        Storage::disk($disk)->put($path, $qrCode->getString());

        return $path;
    }

    /**
     * Obtener QR code como data URL para embeber en HTML
     */
    public function getAsDataUrl(
        string $data,
        int $size = 300,
        ?string $label = null
    ): string {
        $qrCode = (new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            labelText: $label ?? '',
            labelAlignment: LabelAlignment::Center,
        ))->build();

        return $qrCode->getDataUri();
    }

    /**
     * Obtener datos del QR para un documento
     */
    protected function getDocumentQRData(Document $document): string
    {
        // Intentar parsear el qrcode existente como JSON
        $existingData = json_decode($document->qrcode, true);

        if (is_array($existingData)) {
            return json_encode($existingData);
        }

        // Crear nueva estructura de datos
        $companyName = $document->company->name;
        if (is_array($companyName)) {
            $companyName = $document->company->getTranslation('name', app()->getLocale());
        }

        $data = [
            'type' => 'document',
            'id' => $document->id,
            'document_number' => $document->document_number,
            'company' => $companyName,
            'title' => $document->title,
            'url' => route('documents.show', $document->id),
            'created_at' => $document->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
        ];

        return json_encode($data);
    }

    /**
     * Obtener datos del QR para una ubicación física
     */
    protected function getLocationQRData(PhysicalLocation $location): string
    {
        $data = [
            'type' => 'location',
            'id' => $location->id,
            'code' => $location->code,
            'full_path' => $location->full_path,
            'qr_code' => $location->qr_code,
            'capacity_total' => $location->capacity_total,
            'capacity_used' => $location->capacity_used,
            'capacity_percentage' => $location->getCapacityPercentage(),
            'created_at' => $location->created_at?->toDateTimeString(),
        ];

        return json_encode($data);
    }

    /**
     * Generar múltiples QR codes para documentos
     */
    public function generateBatch(
        array $documents,
        string $format = 'png',
        int $size = 300
    ): array {
        $qrCodes = [];

        foreach ($documents as $document) {
            if ($document instanceof Document) {
                $qrCodes[$document->id] = $this->generateForDocument($document, $format, $size);
            }
        }

        return $qrCodes;
    }

    /**
     * Validar datos del QR code
     */
    public function validateData(string $data): bool
    {
        // Validar longitud mínima
        if (strlen($data) < 1) {
            return false;
        }

        // Validar longitud máxima (QR code puede contener hasta ~4000 caracteres)
        if (strlen($data) > 4000) {
            return false;
        }

        return true;
    }

    /**
     * Obtener niveles de corrección de errores disponibles
     */
    public function getErrorCorrectionLevels(): array
    {
        return [
            'low' => 'Bajo (~7%)',
            'medium' => 'Medio (~15%)',
            'quartile' => 'Alto (~25%)',
            'high' => 'Muy Alto (~30%) - Recomendado',
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
            'base64' => 'Base64',
        ];
    }

    /**
     * Obtener tamaños recomendados
     */
    public function getRecommendedSizes(): array
    {
        return [
            'small' => 150,
            'medium' => 300,
            'large' => 500,
            'xlarge' => 1000,
        ];
    }
}
