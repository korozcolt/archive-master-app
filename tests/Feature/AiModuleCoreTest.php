<?php

use App\Models\CompanyAiSetting;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores company ai settings with encrypted api key cast', function () {
    $setting = CompanyAiSetting::factory()->create([
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-test-1234',
        'is_enabled' => true,
    ]);

    expect($setting->provider)->toBe('openai');
    expect($setting->api_key_encrypted)->toBe('sk-test-1234');
    expect($setting->is_enabled)->toBeTrue();
    $this->assertDatabaseHas('company_ai_settings', [
        'id' => $setting->id,
        'provider' => 'openai',
        'is_enabled' => true,
    ]);
});

it('creates ai run linked to document and version', function () {
    $version = DocumentVersion::factory()->create();
    $run = DocumentAiRun::factory()->create([
        'company_id' => $version->document->company_id,
        'document_id' => $version->document_id,
        'document_version_id' => $version->id,
        'task' => 'summarize',
        'status' => 'queued',
    ]);

    expect($run->document->id)->toBe($version->document_id);
    expect($run->documentVersion->id)->toBe($version->id);
    expect($run->task)->toBe('summarize');
});

it('stores output as one to one record per ai run', function () {
    $run = DocumentAiRun::factory()->create();
    $output = DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
        'summary_md' => 'Resumen ejecutivo',
    ]);

    expect($run->output)->not->toBeNull();
    expect($run->output->id)->toBe($output->id);
    expect($output->run->id)->toBe($run->id);
});
