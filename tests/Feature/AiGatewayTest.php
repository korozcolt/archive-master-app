<?php

use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AI\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
    config()->set('ai.mock_mode', true);

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

it('uses nvidia provider for summarize when company config is nvidia', function () {
    config()->set('ai.mock_mode', false);

    Http::fake([
        'https://integrate.api.nvidia.com/v1/chat/completions' => Http::response([
            'model' => 'moonshotai/kimi-k2.6',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary_md' => 'Resumen generado por NVIDIA.',
                            'executive_bullets' => ['Punto clave'],
                            'suggested_tags' => ['finanzas'],
                            'entities' => ['numbers' => ['123']],
                            'confidence' => ['classification' => 0.91],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 10,
                'total_tokens' => 30,
            ],
        ]),
    ]);

    $company = Company::factory()->create();
    CompanyAiSetting::factory()->create([
        'company_id' => $company->id,
        'provider' => 'nvidia',
        'api_key_encrypted' => 'nvapi-test',
        'is_enabled' => true,
    ]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'title' => 'Registro presupuestal',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'content' => 'Registro presupuestal con valor 123.',
    ]);

    $result = app(AiGateway::class)->summarize($version);

    expect($result['provider'])->toBe('nvidia');
    expect($result['model'])->toBe('moonshotai/kimi-k2.6');
    expect($result['summary_md'])->toBe('Resumen generado por NVIDIA.');
    expect($result['tokens_in'])->toBe(20);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer nvapi-test')
        && $request['model'] === 'moonshotai/kimi-k2.6'
        && $request['stream'] === false);
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
