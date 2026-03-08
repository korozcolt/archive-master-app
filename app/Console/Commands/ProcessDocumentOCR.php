<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\OCRService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDocumentOCR extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'documents:process-ocr
                            {--document-id= : ID específico del documento a procesar}
                            {--company-id= : Procesar documentos de una empresa específica}
                            {--limit=10 : Límite de documentos a procesar}
                            {--force : Reprocesar documentos aunque ya tengan OCR}
                            {--language=spa : Idioma para OCR (spa, eng, etc.)}';

    /**
     * The console command description.
     */
    protected $description = 'Procesar documentos con OCR para extraer texto';

    /**
     * Execute the console command.
     */
    public function handle(OCRService $ocrService): int
    {
        $this->info('🔍 Iniciando procesamiento OCR de documentos...');

        // Verificar disponibilidad de OCR
        if (! $ocrService->isTesseractAvailable()) {
            $this->warn('⚠️  Tesseract OCR no está disponible. Usando simulación.');
        }

        // Obtener documentos a procesar
        $documents = $this->getDocumentsToProcess();

        if ($documents->isEmpty()) {
            $this->info('ℹ️  No hay documentos para procesar.');

            return self::SUCCESS;
        }

        $this->info("📄 Procesando {$documents->count()} documentos...");

        $processed = 0;
        $failed = 0;
        $language = $this->option('language');

        $progressBar = $this->output->createProgressBar($documents->count());
        $progressBar->start();

        foreach ($documents as $document) {
            try {
                $this->processDocument($document, $language, $ocrService);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error procesando documento con OCR', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info('✅ Procesamiento completado:');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Documentos procesados', $processed],
                ['Documentos fallidos', $failed],
                ['Idioma utilizado', $language],
                ['Tiempo total', now()->diffForHumans($this->startTime ?? now())],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Obtener documentos a procesar
     */
    private function getDocumentsToProcess()
    {
        $query = Document::query();

        // Filtrar por documento específico
        if ($documentId = $this->option('document-id')) {
            return $query->where('id', $documentId)->get();
        }

        // Filtrar por empresa
        if ($companyId = $this->option('company-id')) {
            $query->where('company_id', $companyId);
        }

        if (! $this->option('force')) {
            $query->where(function ($builder): void {
                $builder->whereNull('metadata->ocr_processed')
                    ->orWhere('metadata->ocr_processed', false);
            });
        }

        // Aplicar límite
        $limit = (int) $this->option('limit');
        $query->limit($limit);

        return $query->with(['company', 'category', 'creator'])->get();
    }

    /**
     * Procesar un documento individual
     */
    private function processDocument(Document $document, string $language, OCRService $ocrService): void
    {
        $this->newLine();
        $this->info("📄 Procesando: {$document->title} (ID: {$document->id})");

        $filePath = $document->file_path;

        if (! is_string($filePath) || trim($filePath) === '') {
            $metadata = $document->metadata ?? [];
            $metadata['ocr_processed'] = true;
            $metadata['ocr_error'] = 'El documento no tiene un archivo asociado para OCR.';
            $metadata['processed_at'] = now()->toISOString();

            $document->metadata = $metadata;
            $document->save();

            $this->warn('⚠️  Documento sin archivo asociado. Se marca con error de OCR.');

            return;
        }

        // Procesar con OCR
        $result = $ocrService->processFile($filePath, $language);

        if ($result['success']) {
            // Actualizar documento con texto extraído
            $metadata = $document->metadata ?? [];
            $metadata['ocr_processed'] = true;
            $metadata['ocr_result'] = [
                'extracted_text' => $result['extracted_text'],
                'confidence' => $result['confidence'],
                'language' => $result['language'],
                'word_count' => $result['metadata']['word_count'],
                'document_type' => $result['metadata']['document_type'],
                'entities' => $result['metadata']['entities'],
                'keywords' => $result['metadata']['keywords'],
                'processed_at' => now()->toISOString(),
            ];
            $metadata['ocr_error'] = null;
            $metadata['ocr_source_path'] = $filePath;

            $document->content = $result['extracted_text'];

            $document->metadata = $metadata;
            $document->save();

            // Reindexar en Scout si está configurado
            if (method_exists($document, 'searchable')) {
                $document->searchable();
            }

            $this->info("✅ Procesado exitosamente (Confianza: {$result['confidence']}%)");
        } else {
            $this->error("❌ Error: {$result['error']}");

            // Marcar como procesado pero con error
            $metadata = $document->metadata ?? [];
            $metadata['ocr_processed'] = true;
            $metadata['ocr_error'] = $result['error'];
            $metadata['processed_at'] = now()->toISOString();

            $document->metadata = $metadata;
            $document->save();
        }
    }
}
