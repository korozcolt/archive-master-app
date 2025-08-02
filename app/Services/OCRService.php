<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Exception;

class OCRService
{
    /**
     * Formatos de archivo soportados para OCR
     */
    const SUPPORTED_FORMATS = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'bmp'];

    /**
     * Idiomas soportados para OCR
     */
    const SUPPORTED_LANGUAGES = [
        'spa' => 'Español',
        'eng' => 'English',
        'fra' => 'Français',
        'deu' => 'Deutsch',
        'ita' => 'Italiano',
        'por' => 'Português',
    ];

    /**
     * Procesar archivo con OCR
     */
    public function processFile(string $filePath, string $language = 'spa'): array
    {
        try {
            // Verificar que el archivo existe
            if (!Storage::exists($filePath)) {
                throw new Exception("Archivo no encontrado: {$filePath}");
            }

            // Obtener información del archivo
            $fileInfo = $this->getFileInfo($filePath);

            // Verificar formato soportado
            if (!in_array($fileInfo['extension'], self::SUPPORTED_FORMATS)) {
                throw new Exception("Formato no soportado: {$fileInfo['extension']}");
            }

            // Procesar según el tipo de archivo
            $extractedText = match ($fileInfo['extension']) {
                'pdf' => $this->processPDF($filePath, $language),
                default => $this->processImage($filePath, $language),
            };

            // Procesar y limpiar el texto extraído
            $processedText = $this->processExtractedText($extractedText);

            // Extraer metadatos del texto
            $metadata = $this->extractMetadata($processedText);

            $result = [
                'success' => true,
                'file_info' => $fileInfo,
                'extracted_text' => $processedText,
                'metadata' => $metadata,
                'language' => $language,
                'processing_time' => microtime(true) - LARAVEL_START,
                'confidence' => $this->calculateConfidence($processedText),
            ];

            Log::info('OCR processing completed successfully', [
                'file_path' => $filePath,
                'text_length' => strlen($processedText),
                'language' => $language,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('OCR processing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'language' => $language,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file_info' => $this->getFileInfo($filePath),
                'language' => $language,
            ];
        }
    }

    /**
     * Procesar archivo PDF con OCR
     */
    private function processPDF(string $filePath, string $language): string
    {
        $fullPath = Storage::path($filePath);

        // Simular procesamiento OCR de PDF
        // En producción, aquí se usaría Tesseract o una API de OCR
        $simulatedText = $this->simulatePDFOCR($fullPath);

        return $simulatedText;
    }

    /**
     * Procesar imagen con OCR
     */
    private function processImage(string $filePath, string $language): string
    {
        $fullPath = Storage::path($filePath);

        // Simular procesamiento OCR de imagen
        // En producción, aquí se usaría Tesseract: tesseract image.jpg output -l spa
        $simulatedText = $this->simulateImageOCR($fullPath);

        return $simulatedText;
    }

    /**
     * Simular OCR de PDF (para demostración)
     */
    private function simulatePDFOCR(string $filePath): string
    {
        // Simular diferentes tipos de documentos
        $documentTypes = [
            'contrato' => "CONTRATO DE SERVICIOS PROFESIONALES\n\nEntre la empresa ACME S.A., representada por su Gerente General, Sr. Juan Pérez, y el proveedor de servicios XYZ Ltda., representado por la Sra. María García, se establece el presente contrato bajo los siguientes términos:\n\n1. OBJETO: Prestación de servicios de consultoría técnica.\n2. PLAZO: 12 meses a partir de la fecha de firma.\n3. VALOR: $50,000 USD pagaderos en cuotas mensuales.\n4. CONDICIONES: El proveedor se compromete a entregar informes mensuales de avance.",

            'factura' => "FACTURA ELECTRÓNICA\n\nNúmero: FAC-2025-001\nFecha: 15 de Enero de 2025\nCliente: Empresa ABC S.A.\nRUT: 12.345.678-9\n\nDETALLE:\n- Servicios de consultoría: $25,000\n- IVA (19%): $4,750\n- TOTAL: $29,750\n\nForma de pago: Transferencia bancaria\nVencimiento: 30 días",

            'reporte' => "REPORTE MENSUAL DE ACTIVIDADES\n\nPeríodo: Enero 2025\nDepartamento: Recursos Humanos\n\nRESUMEN EJECUTIVO:\nDurante el mes de enero se procesaron 45 nuevas contrataciones, se realizaron 12 capacitaciones y se gestionaron 8 procesos disciplinarios.\n\nINDICADORES:\n- Rotación de personal: 3.2%\n- Satisfacción laboral: 87%\n- Cumplimiento de objetivos: 94%",

            'acta' => "ACTA DE REUNIÓN\n\nFecha: 20 de Enero de 2025\nHora: 14:00 hrs\nLugar: Sala de Juntas Principal\n\nASISTENTES:\n- Juan Pérez (Gerente General)\n- María García (Jefe de Ventas)\n- Carlos López (Jefe de Operaciones)\n\nTEMAS TRATADOS:\n1. Revisión de resultados del trimestre\n2. Planificación estratégica 2025\n3. Presupuesto para nuevos proyectos\n\nACUERDOS:\n- Incrementar presupuesto de marketing en 15%\n- Contratar 3 nuevos vendedores\n- Implementar sistema CRM antes de marzo",
        ];

        // Seleccionar tipo de documento basado en el nombre del archivo
        $fileName = strtolower(basename($filePath));

        if (str_contains($fileName, 'contrato')) {
            return $documentTypes['contrato'];
        } elseif (str_contains($fileName, 'factura')) {
            return $documentTypes['factura'];
        } elseif (str_contains($fileName, 'reporte')) {
            return $documentTypes['reporte'];
        } elseif (str_contains($fileName, 'acta')) {
            return $documentTypes['acta'];
        }

        // Documento genérico
        return "DOCUMENTO PROCESADO CON OCR\n\nEste es un texto de ejemplo extraído mediante procesamiento OCR. El contenido real dependería del documento original procesado.\n\nFecha de procesamiento: " . now()->format('d/m/Y H:i:s') . "\nArchivo: " . basename($filePath);
    }

    /**
     * Simular OCR de imagen (para demostración)
     */
    private function simulateImageOCR(string $filePath): string
    {
        return "TEXTO EXTRAÍDO DE IMAGEN\n\nEste texto fue extraído de una imagen mediante tecnología OCR. La calidad del reconocimiento depende de la resolución y claridad de la imagen original.\n\nArchivo procesado: " . basename($filePath) . "\nFecha: " . now()->format('d/m/Y H:i:s');
    }

    /**
     * Procesar y limpiar texto extraído
     */
    private function processExtractedText(string $rawText): string
    {
        // Limpiar texto
        $text = trim($rawText);

        // Normalizar espacios en blanco
        $text = preg_replace('/\s+/', ' ', $text);

        // Normalizar saltos de línea
        $text = preg_replace('/\n+/', "\n", $text);

        // Remover caracteres especiales problemáticos
        $text = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\n]/u', '', $text);

        return $text;
    }

    /**
     * Extraer metadatos del texto
     */
    private function extractMetadata(string $text): array
    {
        $metadata = [
            'word_count' => str_word_count($text),
            'character_count' => strlen($text),
            'line_count' => substr_count($text, "\n") + 1,
            'language_detected' => $this->detectLanguage($text),
            'document_type' => $this->detectDocumentType($text),
            'entities' => $this->extractEntities($text),
            'keywords' => $this->extractKeywords($text),
        ];

        return $metadata;
    }

    /**
     * Detectar idioma del texto
     */
    private function detectLanguage(string $text): string
    {
        // Palabras comunes en español
        $spanishWords = ['el', 'la', 'de', 'que', 'y', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'del'];

        // Palabras comunes en inglés
        $englishWords = ['the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at'];

        $words = str_word_count(strtolower($text), 1);

        $spanishCount = count(array_intersect($words, $spanishWords));
        $englishCount = count(array_intersect($words, $englishWords));

        return $spanishCount > $englishCount ? 'spa' : 'eng';
    }

    /**
     * Detectar tipo de documento
     */
    private function detectDocumentType(string $text): string
    {
        $text = strtolower($text);

        if (str_contains($text, 'contrato') || str_contains($text, 'acuerdo')) {
            return 'contrato';
        } elseif (str_contains($text, 'factura') || str_contains($text, 'invoice')) {
            return 'factura';
        } elseif (str_contains($text, 'reporte') || str_contains($text, 'informe')) {
            return 'reporte';
        } elseif (str_contains($text, 'acta') || str_contains($text, 'reunión')) {
            return 'acta';
        } elseif (str_contains($text, 'carta') || str_contains($text, 'comunicación')) {
            return 'carta';
        }

        return 'documento';
    }

    /**
     * Extraer entidades del texto
     */
    private function extractEntities(string $text): array
    {
        $entities = [];

        // Extraer fechas
        if (preg_match_all('/\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2} de \w+ de \d{4}/', $text, $matches)) {
            $entities['dates'] = array_unique($matches[0]);
        }

        // Extraer números de teléfono
        if (preg_match_all('/\+?[\d\s\-\(\)]{10,}/', $text, $matches)) {
            $entities['phones'] = array_unique($matches[0]);
        }

        // Extraer emails
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $entities['emails'] = array_unique($matches[0]);
        }

        // Extraer montos
        if (preg_match_all('/\$[\d,]+\.?\d*|[\d,]+\.?\d*\s*(USD|EUR|CLP|ARS)/', $text, $matches)) {
            $entities['amounts'] = array_unique($matches[0]);
        }

        return $entities;
    }

    /**
     * Extraer palabras clave
     */
    private function extractKeywords(string $text, int $limit = 10): array
    {
        // Palabras comunes a ignorar
        $stopWords = ['el', 'la', 'de', 'que', 'y', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'del', 'los', 'las', 'una', 'este', 'esta', 'estos', 'estas'];

        $words = str_word_count(strtolower($text), 1);
        $words = array_filter($words, fn($word) => strlen($word) > 3 && !in_array($word, $stopWords));

        $wordCount = array_count_values($words);
        arsort($wordCount);

        return array_slice(array_keys($wordCount), 0, $limit);
    }

    /**
     * Calcular confianza del OCR
     */
    private function calculateConfidence(string $text): float
    {
        // Simular cálculo de confianza basado en características del texto
        $confidence = 85.0; // Base

        // Ajustar según longitud del texto
        $wordCount = str_word_count($text);
        if ($wordCount > 100) {
            $confidence += 5.0;
        } elseif ($wordCount < 20) {
            $confidence -= 10.0;
        }

        // Ajustar según presencia de caracteres especiales problemáticos
        $specialChars = preg_match_all('/[^\p{L}\p{N}\p{P}\p{Z}\n]/u', $text);
        if ($specialChars > 0) {
            $confidence -= ($specialChars * 2);
        }

        return max(0.0, min(100.0, $confidence));
    }

    /**
     * Obtener información del archivo
     */
    private function getFileInfo(string $filePath): array
    {
        $fullPath = Storage::path($filePath);

        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size' => Storage::size($filePath),
            'mime_type' => Storage::mimeType($filePath),
            'last_modified' => Storage::lastModified($filePath),
        ];
    }

    /**
     * Verificar si Tesseract está disponible
     */
    public function isTesseractAvailable(): bool
    {
        // En producción, verificar si Tesseract está instalado
        // exec('tesseract --version', $output, $returnCode);
        // return $returnCode === 0;

        // Para demostración, simular que está disponible
        return true;
    }

    /**
     * Obtener idiomas disponibles
     */
    public function getAvailableLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /**
     * Obtener formatos soportados
     */
    public function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }

    /**
     * Procesar múltiples archivos
     */
    public function processMultipleFiles(array $filePaths, string $language = 'spa'): array
    {
        $results = [];

        foreach ($filePaths as $filePath) {
            $results[] = $this->processFile($filePath, $language);
        }

        return [
            'total_files' => count($filePaths),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ];
    }
}
