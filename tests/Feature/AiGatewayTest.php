<?php

use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AI\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses openai provider for summarize when company config is openai', function () {
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-test',
        'is_enabled' => true,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'title' => 'Contrato de servicios',
        'description' => 'Documento contractual',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'content' => 'Este contrato define cláusulas de prestación de servicios y vigencia anual.',
    ]);

    $result = app(AiGateway::class)->summarize($version);

    expect($result['provider'])->toBe('openai');
    expect($result)->toHaveKeys(['summary_md', 'executive_bullets']);
});

it('uses gemini provider for classify when company config is gemini', function () {
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'gemini',
        'api_key_encrypted' => 'sk-gemini-test',
        'is_enabled' => true,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'title' => 'Solicitud interna',
        'description' => 'Solicitud de trámite administrativo',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'content' => 'Solicitud pendiente de aprobación para trámite administrativo.',
    ]);

    $result = app(AiGateway::class)->classify($version);

    expect($result['provider'])->toBe('gemini');
    expect($result)->toHaveKey('suggested_tags');
});

it('throws when ai is disabled or provider is none', function () {
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'none',
        'is_enabled' => false,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
    ]);

    app(AiGateway::class)->summarize($version);
})->throws(RuntimeException::class, 'La IA no está habilitada para esta compañía.');

it('redacts pii from input before summarize when redact_pii is enabled', function () {
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-test',
        'is_enabled' => true,
        'redact_pii' => true,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'title' => 'Solicitud con datos sensibles',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'content' => 'Contacto: persona@example.com y teléfono +57 3001234567.',
    ]);

    $result = app(AiGateway::class)->summarize($version);

    expect($result['summary_md'])->toContain('[REDACTED_EMAIL]');
    expect($result['summary_md'])->toContain('[REDACTED_PHONE]');
});

it('keeps raw input when redact_pii is disabled', function () {
    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-test',
        'is_enabled' => true,
        'redact_pii' => false,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'title' => 'Solicitud sin redacción',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'content' => 'Contacto: persona@example.com y teléfono +57 3001234567.',
    ]);

    $result = app(AiGateway::class)->summarize($version);

    expect($result['summary_md'])->toContain('persona@example.com');
});
