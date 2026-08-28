<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\OCRService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentOcr implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * Los escaneos del archivo superan con frecuencia las 100 páginas, y cada
     * una exige renderizado a imagen más OCR. Con 180 segundos esos documentos
     * morían por SIGKILL antes de terminar: su texto nunca se extraía, fallaban
     * de forma indefinida, y dejaban atrás su directorio temporal —un SIGKILL
     * no permite ejecutar el bloque finally de OCRService que lo limpia.
     *
     * Debe mantenerse por debajo de REDIS_QUEUE_RETRY_AFTER (1200 s en
     * producción), o la cola reencolaría el trabajo mientras aún se ejecuta y
     * el mismo documento se procesaría por duplicado.
     */
    public int $timeout = 900;

    public function __construct(
        public int $documentId,
        public bool $force = false,
        public string $language = 'spa',
    ) {
        $this->onQueue('document-processing');
    }

    public function handle(OCRService $ocrService): void
    {
        $document = Document::query()->find($this->documentId);

        if (! $document) {
            return;
        }

        $filePath = $document->file_path;

        if (! is_string($filePath) || trim($filePath) === '') {
            $this->storeOcrError($document, 'El documento no tiene un archivo asociado para OCR.');

            return;
        }

        $fingerprint = $this->buildFingerprint($filePath);
        $metadata = $document->metadata ?? [];
        $storedFingerprint = data_get($metadata, 'ocr_source_fingerprint');
        $alreadyProcessed = (bool) data_get($metadata, 'ocr_processed', false);

        if (! $this->force && $alreadyProcessed && $storedFingerprint === $fingerprint && filled($document->content) && empty(data_get($metadata, 'ocr_error'))) {
            return;
        }

        $result = $ocrService->processFile($filePath, $this->language);

        if (! ($result['success'] ?? false)) {
            $this->storeOcrError($document, (string) ($result['error'] ?? 'No se pudo procesar OCR.'), $fingerprint);

            return;
        }

        $extractedText = trim((string) ($result['extracted_text'] ?? ''));

        if ($extractedText === '') {
            $this->storeOcrError($document, 'OCR no extrajo texto útil del archivo.', $fingerprint);

            return;
        }

        $metadata['ocr_processed'] = true;
        $metadata['ocr_error'] = null;
        $metadata['ocr_source_path'] = $filePath;
        $metadata['ocr_source_fingerprint'] = $fingerprint;
        $metadata['ocr_result'] = [
            'extracted_text' => $extractedText,
            'confidence' => $result['confidence'] ?? null,
            'language' => $result['language'] ?? $this->language,
            'word_count' => data_get($result, 'metadata.word_count'),
            'document_type' => data_get($result, 'metadata.document_type'),
            'entities' => data_get($result, 'metadata.entities', []),
            'keywords' => data_get($result, 'metadata.keywords', []),
            'processed_at' => now()->toISOString(),
        ];

        $document->forceFill([
            'content' => $extractedText,
            'metadata' => $metadata,
        ])->save();

        if (method_exists($document, 'searchable')) {
            $document->searchable();
        }

        Log::info('OCR automático completado para documento.', [
            'document_id' => $document->id,
            'file_path' => $filePath,
        ]);
    }

    private function buildFingerprint(string $filePath): string
    {
        $disk = config('documents.files.storage_disk', config('filesystems.default', 'local'));

        if (! Storage::disk($disk)->exists($filePath)) {
            return sha1($filePath.'|missing');
        }

        return sha1(implode('|', [
            $filePath,
            (string) Storage::disk($disk)->size($filePath),
            (string) Storage::disk($disk)->lastModified($filePath),
        ]));
    }

    private function storeOcrError(Document $document, string $error, ?string $fingerprint = null): void
    {
        $metadata = $document->metadata ?? [];
        $metadata['ocr_processed'] = true;
        $metadata['ocr_error'] = $error;
        $metadata['ocr_source_path'] = $document->file_path;
        $metadata['ocr_source_fingerprint'] = $fingerprint;
        $metadata['processed_at'] = now()->toISOString();

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        Log::warning('OCR automático falló para documento.', [
            'document_id' => $document->id,
            'error' => $error,
        ]);
    }
}
