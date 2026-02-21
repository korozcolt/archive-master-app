<?php

use App\Events\DocumentVersionCreated;
use App\Jobs\RunAiPipelineForDocumentVersion;
use App\Listeners\QueueDocumentVersionAiPipeline;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AI\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeVersionWithAiEnabled(array $settingOverrides = [], array $versionOverrides = []): DocumentVersion
{
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create(array_merge([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-pipeline',
        'is_enabled' => true,
        'daily_doc_limit' => 50,
        'max_pages_per_doc' => 100,
        'store_outputs' => true,
    ], $settingOverrides));

    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = Document::factory()->create([
        'company_id' => $company->id,
        'created_by' => $creator->id,
        'assigned_to' => $creator->id,
        'title' => 'Acta administrativa',
        'description' => 'Documento de pruebas para pipeline IA',
    ]);

    $initialVersion = $document->versions()->latest('version_number')->firstOrFail();
    $initialVersion->update(array_merge([
        'created_by' => $creator->id,
        'content' => 'Contenido base del documento para generar resumen ejecutivo por IA.',
        'metadata' => ['page_count' => 2],
        'is_current' => true,
    ], $versionOverrides));

    return $initialVersion->fresh();
}

it('dispatches document version created event when a version is created', function () {
    Event::fake([DocumentVersionCreated::class]);

    makeVersionWithAiEnabled();

    Event::assertDispatched(DocumentVersionCreated::class);
});

it('queues summarize run and ai pipeline job from document version event listener', function () {
    Queue::fake();

    $version = makeVersionWithAiEnabled();
    $event = new DocumentVersionCreated($version);
    app(QueueDocumentVersionAiPipeline::class)->handle($event);

    $run = DocumentAiRun::query()
        ->where('document_version_id', $version->id)
        ->where('task', 'summarize')
        ->first();

    expect($run)->not->toBeNull();
    expect($run->status)->toBe('queued');
    Queue::assertPushed(RunAiPipelineForDocumentVersion::class);
});

it('processes queued summarize run to success and stores ai output', function () {
    $version = makeVersionWithAiEnabled();
    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('success');
    expect(DocumentAiOutput::query()->where('document_ai_run_id', $run->id)->exists())->toBeTrue();
});

it('marks run as skipped when daily limit is reached', function () {
    $version = makeVersionWithAiEnabled([
        'daily_doc_limit' => 1,
    ]);

    DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'already-processed'),
        'prompt_version' => 'v1.0.0',
    ]);

    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-limit'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('skipped');
    expect($run->error_message)->toContain('límite diario');
});

it('marks run as skipped when successful hash already exists for the version', function () {
    $version = makeVersionWithAiEnabled();

    $existingSuccess = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'seed'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $existingSuccess->id])->handle(app(AiGateway::class));
    $existingSuccess->refresh();
    expect($existingSuccess->status)->toBe('success');

    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-second'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('skipped');
    expect($run->error_message)->toContain('cache por versión');
});

it('marks run as skipped when monthly budget is reached', function () {
    $version = makeVersionWithAiEnabled([
        'monthly_budget_cents' => 100,
    ]);

    DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'cost_cents' => 100,
        'input_hash' => hash('sha256', 'budget-hit'),
        'prompt_version' => 'v1.0.0',
    ]);

    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-budget'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('skipped');
    expect($run->error_message)->toContain('presupuesto mensual');
});

it('marks run as skipped when provider circuit breaker is open', function () {
    $version = makeVersionWithAiEnabled();
    config()->set('ai.resilience.circuit_breaker.failure_threshold', 3);
    Cache::put("ai:circuit:{$version->document->company_id}:openai", 3, now()->addMinutes(10));

    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-circuit'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('skipped');
    expect($run->error_message)->toContain('Circuit breaker');
});

it('marks run as failed and increments provider circuit failures when gateway throws', function () {
    $version = makeVersionWithAiEnabled();
    config()->set('ai.resilience.circuit_breaker.failure_threshold', 5);
    config()->set('ai.resilience.circuit_breaker.cooldown_minutes', 15);

    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-fail'),
        'prompt_version' => 'v1.0.0',
    ]);

    $this->mock(AiGateway::class, function ($mock): void {
        $mock->shouldReceive('summarize')
            ->once()
            ->andThrow(new \RuntimeException('Simulated provider failure'));
    });

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $run->id])->handle(app(AiGateway::class));

    $run->refresh();
    expect($run->status)->toBe('failed');
    expect($run->error_message)->toContain('Simulated provider failure');
    expect(Cache::get("ai:circuit:{$version->document->company_id}:openai"))->toBe(1);
});

it('opens provider circuit after configured failure threshold', function () {
    $version = makeVersionWithAiEnabled();
    config()->set('ai.resilience.circuit_breaker.failure_threshold', 1);
    config()->set('ai.resilience.circuit_breaker.cooldown_minutes', 15);

    $failedRun = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-first-fail'),
        'prompt_version' => 'v1.0.0',
    ]);

    $this->mock(AiGateway::class, function ($mock): void {
        $mock->shouldReceive('summarize')
            ->once()
            ->andThrow(new \RuntimeException('Provider failed first run'));
    });

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $failedRun->id])->handle(app(AiGateway::class));

    $failedRun->refresh();
    expect($failedRun->status)->toBe('failed');

    $nextRun = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'queued',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'pending-second-after-threshold'),
        'prompt_version' => 'v1.0.0',
    ]);

    app(RunAiPipelineForDocumentVersion::class, ['runId' => $nextRun->id])->handle(app(AiGateway::class));

    $nextRun->refresh();
    expect($nextRun->status)->toBe('skipped');
    expect($nextRun->error_message)->toContain('Circuit breaker');
});
