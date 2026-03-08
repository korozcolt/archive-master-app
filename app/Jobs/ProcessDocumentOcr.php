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

    public int $timeout = 180;

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

        $metadata['ocr_processed'] = true;
        $metadata['ocr_error'] = null;
        $metadata['ocr_source_path'] = $filePath;
        $metadata['ocr_source_fingerprint'] = $fingerprint;
        $metadata['ocr_result'] = [
            'extracted_text' => $result['extracted_text'],
            'confidence' => $result['confidence'] ?? null,
            'language' => $result['language'] ?? $this->language,
            'word_count' => data_get($result, 'metadata.word_count'),
            'document_type' => data_get($result, 'metadata.document_type'),
            'entities' => data_get($result, 'metadata.entities', []),
            'keywords' => data_get($result, 'metadata.keywords', []),
            'processed_at' => now()->toISOString(),
        ];

        $document->forceFill([
            'content' => (string) ($result['extracted_text'] ?? ''),
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
        if (! Storage::exists($filePath)) {
            return sha1($filePath.'|missing');
        }

        return sha1(implode('|', [
            $filePath,
            (string) Storage::size($filePath),
            (string) Storage::lastModified($filePath),
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
