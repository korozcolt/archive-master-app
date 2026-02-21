<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Str;

class OpenAiProvider implements AiProviderContract
{
    private array $configuration = [];

    public function withConfiguration(array $configuration): static
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function summarize(string $text, array $context = []): array
    {
        return [
            'provider' => 'openai',
            'model' => $this->configuration['model'] ?? config('ai.providers.openai.default_model'),
            'summary_md' => Str::limit(trim($text), 600),
            'executive_bullets' => $this->buildBullets($text),
        ];
    }

    public function extractMetadata(string $text, array $context = []): array
    {
        return [
            'provider' => 'openai',
            'entities' => [
                'dates' => $this->extractDates($text),
                'emails' => $this->extractEmails($text),
            ],
        ];
    }

    public function classify(string $text, array $context = []): array
    {
        return [
            'provider' => 'openai',
            'suggested_tags' => $this->suggestTags($text),
            'confidence' => [
                'classification' => 0.75,
            ],
        ];
    }

    public function testConnection(): array
    {
        return [
            'ok' => true,
            'provider' => 'openai',
            'model' => $this->configuration['model'] ?? config('ai.providers.openai.default_model'),
            'mode' => config('ai.mock_mode') ? 'mock' : 'live',
        ];
    }

    private function buildBullets(string $text): array
    {
        $sentences = preg_split('/(?<=[\.\!\?])\s+/', trim($text)) ?: [];
        $sentences = array_filter($sentences);

        if (count($sentences) === 0) {
            return ['No se encontró contenido suficiente para resumen.'];
        }

        return array_slice(array_values($sentences), 0, 3);
    }

    private function suggestTags(string $text): array
    {
        $normalized = Str::lower($text);
        $tags = [];

        if (Str::contains($normalized, ['contrato', 'cláusula', 'acuerdo'])) {
            $tags[] = 'contrato';
        }

        if (Str::contains($normalized, ['factura', 'iva', 'total'])) {
            $tags[] = 'finanzas';
        }

        if (Str::contains($normalized, ['urgente', 'inmediato', 'prioridad'])) {
            $tags[] = 'urgente';
        }

        return array_values(array_unique($tags ?: ['general']));
    }

    private function extractDates(string $text): array
    {
        preg_match_all('/\b\d{2}[\/\-]\d{2}[\/\-]\d{4}\b/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
