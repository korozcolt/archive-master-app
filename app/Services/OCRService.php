<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OCRService
{
    protected float $startedAt;

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

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    /**
     * Procesar archivo con OCR
     */
    public function processFile(string $filePath, string $language = 'spa'): array
    {
        try {
            $disk = $this->storageDisk();
            // Verificar que el archivo existe
            if (! Storage::disk($disk)->exists($filePath)) {
                throw new Exception("Archivo no encontrado: {$filePath}");
            }

            // Obtener información del archivo
            $fileInfo = $this->getFileInfo($filePath, $disk);

            // Verificar formato soportado
            if (! in_array($fileInfo['extension'], self::SUPPORTED_FORMATS)) {
                throw new Exception("Formato no soportado: {$fileInfo['extension']}");
            }

            // Procesar según el tipo de archivo
            $extractedText = match ($fileInfo['extension']) {
                'pdf' => $this->processPDF($filePath, $language, $disk),
                default => $this->processImage($filePath, $language, $disk),
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
                'processing_time' => microtime(true) - $this->startedAt,
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
                'file_info' => $this->getFileInfo($filePath, $this->storageDisk()),
                'language' => $language,
            ];
        }
    }

    /**
     * Procesar archivo PDF con OCR
     */
    private function processPDF(string $filePath, string $language, string $disk): string
    {
        $fullPath = Storage::disk($disk)->path($filePath);

        // Intentar extraer texto nativo del PDF primero
        $textOutput = shell_exec('pdftotext '.escapeshellarg($fullPath).' - 2>/dev/null');

        // Limpiar form feeds y caracteres de control antes de validar si está vacío
        $cleanOutput = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $textOutput ?? '');

        if (! empty(trim($cleanOutput))) {
            // PDF tiene texto nativo, no necesita OCR
            return trim($textOutput);
        }

        // Si no tiene texto nativo, aplicar OCR usando Tesseract
        return $this->processPDFWithTesseract($fullPath, $language);
    }

    /**
     * Procesar imagen con OCR
     */
    private function processImage(string $filePath, string $language, string $disk): string
    {
        $fullPath = Storage::disk($disk)->path($filePath);

        // Mapear código de idioma a código de Tesseract
        $tesseractLang = $this->mapLanguageToTesseract($language);

        // Ejecutar Tesseract
        $outputFile = tempnam(sys_get_temp_dir(), 'ocr');
        $command = sprintf(
            'tesseract %s %s -l %s 2>&1',
            escapeshellarg($fullPath),
            escapeshellarg($outputFile),
            escapeshellarg($tesseractLang)
        );

        exec($command, $output, $returnCode);

        // Leer el archivo de salida
        $textFile = $outputFile.'.txt';
        $extractedText = '';

        if (file_exists($textFile)) {
            $extractedText = file_get_contents($textFile);
            unlink($textFile);
        }

        // Limpiar archivo temporal
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }

        if ($returnCode !== 0) {
            throw new \Exception('Error al procesar imagen con Tesseract: '.implode("\n", $output));
        }

        return trim($extractedText);
    }

    /**
     * Procesar PDF escaneado con Tesseract
     */
    private function processPDFWithTesseract(string $fullPath, string $language): string
    {
        $tesseractLang = $this->mapLanguageToTesseract($language);
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_pdf_'.uniqid();

        if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }

        try {
            // Convertir páginas a imágenes PNG usando pdftoppm (150 DPI)
            $command = sprintf(
                'pdftoppm -png -r 150 %s %s',
                escapeshellarg($fullPath),
                escapeshellarg($tempDir.DIRECTORY_SEPARATOR.'page')
            );
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Error al convertir PDF a imágenes con pdftoppm: '.implode("\n", $output));
            }

            // Buscar imágenes de páginas generadas
            $images = glob($tempDir.DIRECTORY_SEPARATOR.'page-*.png');
            sort($images);

            if (empty($images)) {
                return '';
            }

            $fullExtractedText = '';

            foreach ($images as $imagePath) {
                // Ejecutar Tesseract en cada página
                $outputFile = tempnam(sys_get_temp_dir(), 'ocr_page');
                $tessCommand = sprintf(
                    'tesseract %s %s -l %s 2>&1',
                    escapeshellarg($imagePath),
                    escapeshellarg($outputFile),
                    escapeshellarg($tesseractLang)
                );
                exec($tessCommand, $tessOutput, $tessReturnCode);

                $textFile = $outputFile.'.txt';
                if (file_exists($textFile)) {
                    $pageText = file_get_contents($textFile);
                    $fullExtractedText .= $pageText."\n\n";
                    unlink($textFile);
                }

                if (file_exists($outputFile)) {
                    unlink($outputFile);
                }

                unlink($imagePath);
            }

            return trim($fullExtractedText);
        } catch (\Throwable $e) {
            Log::error('OCR PDF with Tesseract failed', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            if (is_dir($tempDir)) {
                // Limpiar cualquier archivo restante
                $remainingFiles = glob($tempDir.DIRECTORY_SEPARATOR.'*');
                foreach ($remainingFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($tempDir);
            }
        }
    }

    /**
     * Mapear código de idioma a código de Tesseract
     */
    private function mapLanguageToTesseract(string $language): string
    {
        $languageMap = [
            'es' => 'spa',      // Español
            'spa' => 'spa',     // Español (Tesseract)
            'en' => 'eng',      // Inglés
            'eng' => 'eng',     // Inglés (Tesseract)
            'fr' => 'fra',      // Francés
            'fra' => 'fra',     // Francés (Tesseract)
            'de' => 'deu',      // Alemán
            'deu' => 'deu',     // Alemán (Tesseract)
            'it' => 'ita',      // Italiano
            'ita' => 'ita',     // Italiano (Tesseract)
            'pt' => 'por',      // Portugués
            'por' => 'por',     // Portugués (Tesseract)
        ];

        return $languageMap[$language] ?? 'eng';
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
        $words = array_filter($words, fn ($word) => strlen($word) > 3 && ! in_array($word, $stopWords));

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
    private function getFileInfo(string $filePath, ?string $disk = null): array
    {
        $disk ??= $this->storageDisk();

        if (! Storage::disk($disk)->exists($filePath)) {
            return [
                'path' => $filePath,
                'name' => basename($filePath),
                'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
                'size' => null,
                'mime_type' => null,
                'last_modified' => null,
            ];
        }

        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size' => Storage::disk($disk)->size($filePath),
            'mime_type' => Storage::disk($disk)->mimeType($filePath),
            'last_modified' => Storage::disk($disk)->lastModified($filePath),
        ];
    }

    private function storageDisk(): string
    {
        return config('documents.files.storage_disk', config('filesystems.default', 'local'));
    }

    /**
     * Verificar si Tesseract está disponible
     */
    public function isTesseractAvailable(): bool
    {
        $path = trim((string) shell_exec('command -v tesseract 2>/dev/null'));

        return $path !== '';
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
            'successful' => count(array_filter($results, fn ($r) => $r['success'])),
            'failed' => count(array_filter($results, fn ($r) => ! $r['success'])),
            'results' => $results,
        ];
    }
}
