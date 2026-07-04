<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AI\AiGateway;
use App\Services\OCRService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SimulateAccountingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:simulate-accounting 
                            {--year=2026 : El año de vigencia contable a simular} 
                            {--limit=50 : Límite de documentos a procesar}
                            {--company=1 : ID de la empresa o slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simula transacciones contables a partir de documentos digitalizados mediante OCR e IA (Gemini)';

    /**
     * Execute the console command.
     */
    public function handle(OCRService $ocrService, AiGateway $aiGateway): int
    {
        $year = (int) $this->option('year');
        $limit = (int) $this->option('limit');
        $companyVal = $this->option('company');

        // Buscar empresa
        $company = is_numeric($companyVal)
            ? Company::find($companyVal)
            : Company::where('slug', $companyVal)->first();

        if (! $company) {
            $this->error("Empresa '{$companyVal}' no encontrada.");

            return self::FAILURE;
        }

        $this->info("=== Simulación Contable Aguas de Sucre - Año {$year} ===");
        $this->info("Empresa: {$company->name}");

        // Definir categorías contables relevantes
        $categoryIds = [4, 5, 6, 7, 8, 31, 32, 33, 34, 35, 38];

        $this->info('Buscando documentos...');
        $query = Document::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereIn('category_id', $categoryIds);

        $totalDocs = $query->count();
        $this->info("Total documentos encontrados para el año {$year}: {$totalDocs}");

        if ($totalDocs === 0) {
            $this->warn('No hay documentos para procesar.');

            return self::SUCCESS;
        }

        $documents = $query->latest('id')->take($limit)->get();
        $this->info("Procesando los últimos {$documents->count()} documentos...");

        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();

        $transactions = [];
        $processedCount = 0;
        $failedCount = 0;
        $ocrTriggeredCount = 0;

        foreach ($documents as $doc) {
            try {
                // Asegurar que el documento tiene una versión actual
                $version = $doc->currentVersion ?? $doc->versions()->where('is_current', true)->first();
                if (! $version) {
                    $version = DocumentVersion::create([
                        'document_id' => $doc->id,
                        'version_number' => 1,
                        'file_path' => $doc->file_path,
                        'is_current' => true,
                        'metadata' => $doc->metadata ?? [],
                    ]);
                }

                // Si no tiene contenido, procesar OCR
                $content = trim($doc->content ?? '');
                if ($content === '') {
                    $ocrResult = $ocrService->processFile($doc->file_path, 'spa');
                    if ($ocrResult['success'] ?? false) {
                        $content = $ocrResult['extracted_text'];
                        $doc->forceFill([
                            'content' => $content,
                        ])->save();

                        $version->forceFill([
                            'content' => $content,
                        ])->save();
                        $ocrTriggeredCount++;
                    }
                }

                if (trim($content) === '') {
                    $failedCount++;
                    $bar->advance();

                    continue;
                }

                // Llamar a la IA para extraer datos contables
                $accountingData = $aiGateway->extractAccounting($version);

                // Agregar datos complementarios
                $accountingData['document_id'] = $doc->id;
                $accountingData['document_title'] = $doc->title;

                $transactions[] = $accountingData;
                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                \Illuminate\Support\Facades\Log::warning("Error procesando simulación para documento ID {$doc->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Exportar a JSON
        $fileName = "accounting_simulation_{$company->id}_{$year}_".now()->format('Ymd_His').'.json';
        $jsonContent = json_encode([
            'meta' => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'year' => $year,
                'generated_at' => now()->toIso8601String(),
                'total_processed' => $processedCount,
                'total_failed' => $failedCount,
                'ocr_runs' => $ocrTriggeredCount,
            ],
            'transactions' => $transactions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk('local')->put($fileName, $jsonContent);
        $outputPath = Storage::disk('local')->path($fileName);

        $this->info('=== Simulación Finalizada ===');
        $this->info("Procesados con éxito: {$processedCount}");
        $this->info("Fallidos/Omitidos: {$failedCount}");
        $this->info("Documentos que requirieron OCR en vivo: {$ocrTriggeredCount}");
        $this->info('Archivo JSON generado con éxito:');
        $this->comment($outputPath);

        return self::SUCCESS;
    }
}
