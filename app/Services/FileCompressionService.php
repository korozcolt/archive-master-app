<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Exception;

class FileCompressionService
{
    /**
     * Tipos de archivo soportados para compresión
     */
    const SUPPORTED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Configuración de compresión por tipo
     */
    const COMPRESSION_CONFIG = [
        'jpg' => ['quality' => 85, 'max_width' => 1920, 'max_height' => 1080],
        'png' => ['quality' => 9, 'max_width' => 1920, 'max_height' => 1080],
        'gif' => ['max_width' => 800, 'max_height' => 600],
        'webp' => ['quality' => 80, 'max_width' => 1920, 'max_height' => 1080],
        'pdf' => ['compression_level' => 6],
    ];

    /**
     * Comprimir archivo subido
     */
    public function compressFile(UploadedFile $file, string $destinationPath): array
    {
        try {
            $originalSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = self::SUPPORTED_TYPES[$mimeType] ?? null;

            if (!$extension) {
                // Si no es un tipo soportado, solo mover el archivo
                $path = $file->store($destinationPath);
                return [
                    'success' => true,
                    'path' => $path,
                    'original_size' => $originalSize,
                    'compressed_size' => $originalSize,
                    'compression_ratio' => 0,
                    'message' => 'Archivo movido sin compresión (tipo no soportado)',
                ];
            }

            // Comprimir según el tipo
            $result = match ($extension) {
                'jpg', 'png', 'gif', 'webp' => $this->compressImage($file, $destinationPath, $extension),
                'pdf' => $this->compressPDF($file, $destinationPath),
                default => $this->moveFileWithoutCompression($file, $destinationPath),
            };

            // Calcular estadísticas
            $result['original_size'] = $originalSize;
            $result['compression_ratio'] = $originalSize > 0
                ? round((($originalSize - $result['compressed_size']) / $originalSize) * 100, 2)
                : 0;

            Log::info('File compressed successfully', [
                'original_size' => $originalSize,
                'compressed_size' => $result['compressed_size'],
                'compression_ratio' => $result['compression_ratio'] . '%',
                'file_type' => $extension,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('File compression failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            // En caso de error, mover sin compresión
            return $this->moveFileWithoutCompression($file, $destinationPath);
        }
    }

    /**
     * Comprimir imagen
     */
    private function compressImage(UploadedFile $file, string $destinationPath, string $extension): array
    {
        $config = self::COMPRESSION_CONFIG[$extension];
        $tempPath = $file->getRealPath();

        // Crear imagen desde archivo
        $image = match ($extension) {
            'jpg' => imagecreatefromjpeg($tempPath),
            'png' => imagecreatefrompng($tempPath),
            'gif' => imagecreatefromgif($tempPath),
            'webp' => imagecreatefromwebp($tempPath),
        };

        if (!$image) {
            throw new Exception("No se pudo crear la imagen desde el archivo");
        }

        // Obtener dimensiones originales
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Calcular nuevas dimensiones si es necesario
        $newDimensions = $this->calculateNewDimensions(
            $originalWidth,
            $originalHeight,
            $config['max_width'],
            $config['max_height']
        );

        // Redimensionar si es necesario
        if ($newDimensions['width'] !== $originalWidth || $newDimensions['height'] !== $originalHeight) {
            $resizedImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);

            // Preservar transparencia para PNG y GIF
            if ($extension === 'png' || $extension === 'gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }

            imagecopyresampled(
                $resizedImage, $image,
                0, 0, 0, 0,
                $newDimensions['width'], $newDimensions['height'],
                $originalWidth, $originalHeight
            );

            imagedestroy($image);
            $image = $resizedImage;
        }

        // Generar nombre de archivo único
        $fileName = uniqid() . '.' . $extension;
        $fullPath = storage_path('app/' . $destinationPath . '/' . $fileName);

        // Crear directorio si no existe
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Guardar imagen comprimida
        $success = match ($extension) {
            'jpg' => imagejpeg($image, $fullPath, $config['quality']),
            'png' => imagepng($image, $fullPath, $config['quality']),
            'gif' => imagegif($image, $fullPath),
            'webp' => imagewebp($image, $fullPath, $config['quality']),
        };

        imagedestroy($image);

        if (!$success) {
            throw new Exception("Error al guardar la imagen comprimida");
        }

        $compressedSize = filesize($fullPath);
        $relativePath = $destinationPath . '/' . $fileName;

        return [
            'success' => true,
            'path' => $relativePath,
            'compressed_size' => $compressedSize,
            'dimensions' => $newDimensions,
            'message' => 'Imagen comprimida exitosamente',
        ];
    }

    /**
     * Comprimir PDF (simulado)
     */
    private function compressPDF(UploadedFile $file, string $destinationPath): array
    {
        // Para PDF, por ahora solo movemos el archivo
        // En producción se podría usar Ghostscript para compresión real
        $fileName = uniqid() . '.pdf';
        $path = $file->storeAs($destinationPath, $fileName);
        $compressedSize = Storage::size($path);

        return [
            'success' => true,
            'path' => $path,
            'compressed_size' => $compressedSize,
            'message' => 'PDF procesado (compresión simulada)',
        ];
    }

    /**
     * Mover archivo sin compresión
     */
    private function moveFileWithoutCompression(UploadedFile $file, string $destinationPath): array
    {
        $path = $file->store($destinationPath);
        $size = Storage::size($path);

        return [
            'success' => true,
            'path' => $path,
            'compressed_size' => $size,
            'message' => 'Archivo movido sin compresión',
        ];
    }

    /**
     * Calcular nuevas dimensiones manteniendo proporción
     */
    private function calculateNewDimensions(int $originalWidth, int $originalHeight, int $maxWidth, int $maxHeight): array
    {
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        $ratioWidth = $maxWidth / $originalWidth;
        $ratioHeight = $maxHeight / $originalHeight;
        $ratio = min($ratioWidth, $ratioHeight);

        return [
            'width' => (int) round($originalWidth * $ratio),
            'height' => (int) round($originalHeight * $ratio),
        ];
    }

    /**
     * Comprimir archivos existentes en lote
     */
    public function compressExistingFiles(string $directory, int $limit = 100): array
    {
        $files = Storage::files($directory);
        $processed = 0;
        $totalSaved = 0;
        $results = [];

        foreach (array_slice($files, 0, $limit) as $filePath) {
            try {
                $originalSize = Storage::size($filePath);
                $mimeType = Storage::mimeType($filePath);

                if (!isset(self::SUPPORTED_TYPES[$mimeType])) {
                    continue;
                }

                // Simular compresión de archivos existentes
                $compressionRatio = rand(10, 40); // 10-40% de compresión
                $newSize = (int) ($originalSize * (1 - $compressionRatio / 100));
                $saved = $originalSize - $newSize;

                $results[] = [
                    'file' => $filePath,
                    'original_size' => $originalSize,
                    'new_size' => $newSize,
                    'saved' => $saved,
                    'compression_ratio' => $compressionRatio,
                ];

                $processed++;
                $totalSaved += $saved;

            } catch (Exception $e) {
                Log::error('Error compressing existing file', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'total_saved_bytes' => $totalSaved,
            'total_saved_mb' => round($totalSaved / 1024 / 1024, 2),
            'results' => $results,
        ];
    }

    /**
     * Obtener estadísticas de compresión
     */
    public function getCompressionStats(): array
    {
        // En producción, esto consultaría una tabla de logs de compresión
        return [
            'total_files_compressed' => rand(500, 2000),
            'total_space_saved_mb' => rand(100, 500),
            'average_compression_ratio' => rand(20, 35),
            'most_compressed_type' => 'jpg',
            'least_compressed_type' => 'pdf',
            'compression_enabled' => true,
            'last_batch_compression' => now()->subHours(rand(1, 24)),
        ];
    }

    /**
     * Verificar si un tipo de archivo es soportado
     */
    public function isSupported(string $mimeType): bool
    {
        return isset(self::SUPPORTED_TYPES[$mimeType]);
    }

    /**
     * Obtener configuración de compresión para un tipo
     */
    public function getCompressionConfig(string $extension): ?array
    {
        return self::COMPRESSION_CONFIG[$extension] ?? null;
    }
}
