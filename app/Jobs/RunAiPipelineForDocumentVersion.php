<?php

namespace App\Jobs;

use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use App\Services\AI\AiGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAiPipelineForDocumentVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $runId) {}

    public function handle(AiGateway $aiGateway): void
    {
        $run = DocumentAiRun::query()
            ->with(['documentVersion.document.company.aiSetting'])
            ->find($this->runId);

        if (! $run) {
            return;
        }

        $version = $run->documentVersion;
        if (! $version) {
            $this->markAsFailed($run, 'No se encontró la versión del documento para ejecutar IA.');

            return;
        }

        $setting = $version->document->company->aiSetting;
        if (! $setting || ! $setting->is_enabled || $setting->provider === 'none') {
            $this->markAsSkipped($run, 'IA deshabilitada para la compañía.');

            return;
        }

        if ($this->isCircuitOpen((int) $run->company_id, (string) $run->provider)) {
            $this->markAsSkipped($run, 'Circuit breaker activo para el proveedor IA.');

            return;
        }

        if ($this->exceededDailyLimit($run, (int) $setting->daily_doc_limit)) {
            $this->markAsSkipped($run, 'Se alcanzó el límite diario de documentos para IA.');

            return;
        }

        if ($this->exceededMonthlyBudget($run, $setting->monthly_budget_cents)) {
            $this->markAsSkipped($run, 'Se alcanzó el presupuesto mensual de IA para la compañía.');

            return;
        }

        if ($this->exceededPageLimit($version, (int) $setting->max_pages_per_doc)) {
            $this->markAsSkipped($run, 'El documento excede el límite máximo de páginas para IA.');

            return;
        }

        $promptVersion = (string) config('ai.prompt_versions.summarize', 'v1.0.0');
        $model = $run->provider === 'gemini'
            ? (string) config('ai.providers.gemini.default_model')
            : (string) config('ai.providers.openai.default_model');
        $inputHash = $this->makeInputHash($version, $promptVersion, $run->provider, $model, (bool) $setting->redact_pii);

        $duplicateRun = DocumentAiRun::query()
            ->where('id', '!=', $run->id)
            ->where('company_id', $run->company_id)
            ->where('document_version_id', $run->document_version_id)
            ->where('task', 'summarize')
            ->where('status', 'success')
            ->where('input_hash', $inputHash)
            ->where('prompt_version', $promptVersion)
            ->exists();

        if ($duplicateRun) {
            $run->update([
                'status' => 'skipped',
                'input_hash' => $inputHash,
                'prompt_version' => $promptVersion,
                'model' => $model,
                'error_message' => 'Resultado reutilizado por hash de entrada (cache por versión).',
            ]);

            return;
        }

        $run->update([
            'status' => 'running',
            'input_hash' => $inputHash,
            'prompt_version' => $promptVersion,
            'model' => $model,
            'error_message' => null,
        ]);
        Log::info('ai.pipeline.run.started', [
            'run_id' => $run->id,
            'company_id' => $run->company_id,
            'document_id' => $run->document_id,
            'document_version_id' => $run->document_version_id,
            'provider' => $run->provider,
            'task' => $run->task,
            'prompt_version' => $promptVersion,
        ]);

        try {
            $result = $aiGateway->summarize($version);

            $run->update([
                'status' => 'success',
                'tokens_in' => $result['tokens_in'] ?? null,
                'tokens_out' => $result['tokens_out'] ?? null,
                'cost_cents' => $result['cost_cents'] ?? null,
                'error_message' => null,
            ]);
            $this->resetCircuit((int) $run->company_id, (string) $run->provider);
            Log::info('ai.pipeline.run.succeeded', [
                'run_id' => $run->id,
                'company_id' => $run->company_id,
                'provider' => $run->provider,
                'tokens_in' => $run->tokens_in,
                'tokens_out' => $run->tokens_out,
                'cost_cents' => $run->cost_cents,
            ]);

            if ($setting->store_outputs) {
                DocumentAiOutput::query()->updateOrCreate(
                    ['document_ai_run_id' => $run->id],
                    [
                        'summary_md' => $result['summary_md'] ?? null,
                        'executive_bullets' => $result['executive_bullets'] ?? null,
                        'suggested_tags' => $result['suggested_tags'] ?? null,
                        'entities' => $result['entities'] ?? null,
                        'confidence' => $result['confidence'] ?? null,
                    ]
                );
            }
        } catch (Throwable $exception) {
            $this->markAsFailed($run, $exception->getMessage());
            $this->incrementProviderFailure((int) $run->company_id, (string) $run->provider);
            Log::error('ai.pipeline.run.failed', [
                'run_id' => $run->id,
                'company_id' => $run->company_id,
                'provider' => $run->provider,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function exceededDailyLimit(DocumentAiRun $run, int $dailyLimit): bool
    {
        if ($dailyLimit <= 0) {
            return false;
        }

        $count = DocumentAiRun::query()
            ->where('company_id', $run->company_id)
            ->where('id', '!=', $run->id)
            ->where('task', 'summarize')
            ->whereIn('status', ['queued', 'running', 'success'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $count >= $dailyLimit;
    }

    private function exceededPageLimit(DocumentVersion $version, int $maxPagesPerDoc): bool
    {
        if ($maxPagesPerDoc <= 0) {
            return false;
        }

        $versionMetadata = is_array($version->metadata) ? $version->metadata : [];
        $documentMetadata = is_array($version->document->metadata) ? $version->document->metadata : [];

        $pageCount = (int) ($versionMetadata['page_count']
            ?? $versionMetadata['pages']
            ?? $documentMetadata['page_count']
            ?? $documentMetadata['pages']
            ?? 1);

        return $pageCount > $maxPagesPerDoc;
    }

    private function exceededMonthlyBudget(DocumentAiRun $run, ?int $monthlyBudgetCents): bool
    {
        if (! $monthlyBudgetCents || $monthlyBudgetCents <= 0) {
            return false;
        }

        $spent = (int) DocumentAiRun::query()
            ->where('company_id', $run->company_id)
            ->where('id', '!=', $run->id)
            ->where('task', 'summarize')
            ->where('status', 'success')
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('cost_cents');

        return $spent >= $monthlyBudgetCents;
    }

    private function isCircuitOpen(int $companyId, string $provider): bool
    {
        $threshold = (int) config('ai.resilience.circuit_breaker.failure_threshold', 5);
        if ($threshold <= 0) {
            return false;
        }

        $failures = (int) Cache::get($this->circuitKey($companyId, $provider), 0);

        return $failures >= $threshold;
    }

    private function incrementProviderFailure(int $companyId, string $provider): void
    {
        $threshold = (int) config('ai.resilience.circuit_breaker.failure_threshold', 5);
        if ($threshold <= 0) {
            return;
        }

        $key = $this->circuitKey($companyId, $provider);
        $cooldownMinutes = (int) config('ai.resilience.circuit_breaker.cooldown_minutes', 15);
        $ttl = now()->addMinutes(max(1, $cooldownMinutes));

        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, $ttl);
    }

    private function resetCircuit(int $companyId, string $provider): void
    {
        Cache::forget($this->circuitKey($companyId, $provider));
    }

    private function circuitKey(int $companyId, string $provider): string
    {
        return "ai:circuit:{$companyId}:{$provider}";
    }

    private function makeInputHash(
        DocumentVersion $version,
        string $promptVersion,
        string $provider,
        string $model,
        bool $redactPii
    ): string {
        $payload = [
            'content' => (string) ($version->content ?? ''),
            'document_title' => (string) ($version->document->title ?? ''),
            'document_description' => (string) ($version->document->description ?? ''),
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'redact_pii' => $redactPii,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function markAsSkipped(DocumentAiRun $run, string $message): void
    {
        $run->update([
            'status' => 'skipped',
            'error_message' => $message,
        ]);
        Log::info('ai.pipeline.run.skipped', [
            'run_id' => $run->id,
            'company_id' => $run->company_id,
            'provider' => $run->provider,
            'reason' => $message,
        ]);
    }

    private function markAsFailed(DocumentAiRun $run, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
