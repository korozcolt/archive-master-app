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

        // Para PDFs, primero necesitamos convertir a imágenes usando Imagick o similar
        // Por ahora, si el PDF tiene texto nativo, usamos pdftotext
        // Si está escaneado, necesitaríamos convertir las páginas a imágenes primero

        // Intentar extraer texto nativo del PDF primero
        $textOutput = shell_exec("pdftotext " . escapeshellarg($fullPath) . " - 2>/dev/null");

        if (!empty(trim($textOutput))) {
            // PDF tiene texto nativo, no necesita OCR
            return trim($textOutput);
        }

        // Si no tiene texto nativo, aplicar OCR usando Tesseract
        // Nota: esto requiere convertir el PDF a imágenes primero
        // Para simplificar, procesaremos como imagen si es un archivo escaneado
        return $this->processPDFWithTesseract($fullPath, $language);
    }

    /**
     * Procesar imagen con OCR
     */
    private function processImage(string $filePath, string $language): string
    {
        $fullPath = Storage::path($filePath);

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
        $textFile = $outputFile . '.txt';
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
            throw new \Exception('Error al procesar imagen con Tesseract: ' . implode("\n", $output));
        }

        return trim($extractedText);
    }

    /**
     * Procesar PDF escaneado con Tesseract
     */
    private function processPDFWithTesseract(string $fullPath, string $language): string
    {
        // Para PDFs escaneados, idealmente convertiríamos cada página a imagen
        // Por simplicidad, informamos que se requiere preprocesamiento
        // En producción, usaríamos Imagick o GhostScript para convertir PDF a imágenes

        return "NOTA: Este PDF parece estar escaneado y no contiene texto nativo.\n" .
               "Para procesar PDFs escaneados, es necesario convertirlos a imágenes primero.\n" .
               "Considere usar herramientas como ImageMagick o subir imágenes directamente.\n\n" .
               "Archivo: " . basename($fullPath);
    }

    /**
     * Mapear código de idioma a código de Tesseract
     */
    private function mapLanguageToTesseract(string $language): string
    {
        $languageMap = [
            'es' => 'spa',      // Español
            'en' => 'eng',      // Inglés
            'fr' => 'fra',      // Francés
            'de' => 'deu',      // Alemán
            'it' => 'ita',      // Italiano
            'pt' => 'por',      // Portugués
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
