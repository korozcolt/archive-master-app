<?php

namespace App\Listeners;

use App\Events\DocumentVersionCreated;
use App\Jobs\RunAiPipelineForDocumentVersion;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class QueueDocumentVersionAiPipeline implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 2;

    public string $queue = 'ai-processing';

    public function handle(DocumentVersionCreated $event): void
    {
        $version = $event->documentVersion->loadMissing('document.company.aiSetting');
        $setting = $version->document->company->aiSetting;

        if (! $setting || ! $setting->is_enabled || $setting->provider === 'none') {
            return;
        }

        $promptVersion = (string) config('ai.prompt_versions.summarize', 'v1.0.0');
        $model = $setting->provider === 'gemini'
            ? (string) config('ai.providers.gemini.default_model')
            : (string) config('ai.providers.openai.default_model');

        $run = DocumentAiRun::query()->create([
            'company_id' => $version->document->company_id,
            'document_id' => $version->document_id,
            'document_version_id' => $version->id,
            'triggered_by' => $version->created_by,
            'provider' => $setting->provider,
            'model' => $model,
            'status' => 'queued',
            'task' => 'summarize',
            'input_hash' => $this->makeInputHash($version, $promptVersion, $setting->provider, $model),
            'prompt_version' => $promptVersion,
        ]);

        RunAiPipelineForDocumentVersion::dispatch($run->id)->onQueue('ai-processing');
    }

    private function makeInputHash(
        DocumentVersion $version,
        string $promptVersion,
        string $provider,
        string $model
    ): string {
        $payload = [
            'content' => (string) ($version->content ?? ''),
            'document_title' => (string) ($version->document->title ?? ''),
            'document_description' => (string) ($version->document->description ?? ''),
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'redact_pii' => (bool) ($version->document->company->aiSetting?->redact_pii ?? true),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
