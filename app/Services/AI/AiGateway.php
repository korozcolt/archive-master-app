<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\DocumentVersion;
use App\Services\AI\Contracts\AiProviderContract;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use RuntimeException;

class AiGateway
{
    public function __construct(
        private OpenAiProvider $openAiProvider,
        private GeminiProvider $geminiProvider
    ) {}

    public function summarize(DocumentVersion $version): array
    {
        $setting = $this->resolveEnabledSettingForVersion($version);
        $provider = $this->providerByName($setting->provider)->withConfiguration([
            'api_key' => $setting->api_key_encrypted,
            'model' => $this->defaultModelForProvider($setting->provider),
            'provider' => $setting->provider,
        ]);
        $inputText = $this->resolveInputText($version, (bool) $setting->redact_pii);

        return $provider->summarize($inputText, [
            'document_version_id' => $version->id,
            'document_id' => $version->document_id,
        ]);
    }

    public function extractMetadata(DocumentVersion $version): array
    {
        $setting = $this->resolveEnabledSettingForVersion($version);
        $provider = $this->providerByName($setting->provider)->withConfiguration([
            'api_key' => $setting->api_key_encrypted,
            'model' => $this->defaultModelForProvider($setting->provider),
            'provider' => $setting->provider,
        ]);
        $inputText = $this->resolveInputText($version, (bool) $setting->redact_pii);

        return $provider->extractMetadata($inputText, [
            'document_version_id' => $version->id,
            'document_id' => $version->document_id,
        ]);
    }

    public function classify(DocumentVersion $version): array
    {
        $setting = $this->resolveEnabledSettingForVersion($version);
        $provider = $this->providerByName($setting->provider)->withConfiguration([
            'api_key' => $setting->api_key_encrypted,
            'model' => $this->defaultModelForProvider($setting->provider),
            'provider' => $setting->provider,
        ]);
        $inputText = $this->resolveInputText($version, (bool) $setting->redact_pii);

        return $provider->classify($inputText, [
            'document_version_id' => $version->id,
            'document_id' => $version->document_id,
        ]);
    }

    public function testProvider(Company $company, string $sampleText): array
    {
        $setting = $this->resolveEnabledSetting($company);
        $provider = $this->providerByName($setting->provider)->withConfiguration([
            'api_key' => $setting->api_key_encrypted,
            'model' => $this->defaultModelForProvider($setting->provider),
            'provider' => $setting->provider,
        ]);

        $status = $provider->testConnection();
        $preview = $provider->summarize($sampleText);

        return [
            'status' => $status,
            'preview' => $preview,
        ];
    }

    private function resolveEnabledSettingForVersion(DocumentVersion $version): CompanyAiSetting
    {
        $company = $version->document->company;

        return $this->resolveEnabledSetting($company);
    }

    private function resolveEnabledSetting(Company $company): CompanyAiSetting
    {
        $setting = $company->aiSetting()->first();

        if (! $setting || ! $setting->is_enabled || $setting->provider === 'none') {
            throw new RuntimeException('La IA no está habilitada para esta compañía.');
        }

        if (! $setting->api_key_encrypted) {
            throw new RuntimeException('La compañía no tiene API key configurada para IA.');
        }

        return $setting;
    }

    private function providerByName(string $provider): AiProviderContract
    {
        return match ($provider) {
            'openai' => $this->openAiProvider,
            'gemini' => $this->geminiProvider,
            default => throw new RuntimeException('Proveedor de IA no soportado: '.$provider),
        };
    }

    private function defaultModelForProvider(string $provider): string
    {
        return match ($provider) {
            'openai' => config('ai.providers.openai.default_model'),
            'gemini' => config('ai.providers.gemini.default_model'),
            default => 'unknown',
        };
    }

    private function resolveInputText(DocumentVersion $version, bool $redactPii = true): string
    {
        $content = trim((string) ($version->content ?? ''));

        if ($content !== '') {
            return $redactPii ? $this->redactPii($content) : $content;
        }

        $fallback = trim((string) ($version->document->content ?? ''));
        if ($fallback !== '') {
            return $redactPii ? $this->redactPii($fallback) : $fallback;
        }

        $resolved = trim($version->document->title.' '.$version->document->description);

        return $redactPii ? $this->redactPii($resolved) : $resolved;
    }

    private function redactPii(string $text): string
    {
        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[REDACTED_EMAIL]',
            '/\b(?:\+?\d{1,3}[\s\-]?)?(?:\(?\d{2,4}\)?[\s\-]?)?\d{3,4}[\s\-]?\d{3,4}\b/' => '[REDACTED_PHONE]',
            '/\b\d{6,12}\b/' => '[REDACTED_ID]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;
    }
}
