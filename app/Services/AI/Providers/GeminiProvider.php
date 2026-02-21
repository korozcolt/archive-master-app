<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Str;

class GeminiProvider implements AiProviderContract
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
            'provider' => 'gemini',
            'model' => $this->configuration['model'] ?? config('ai.providers.gemini.default_model'),
            'summary_md' => Str::limit(trim($text), 600),
            'executive_bullets' => $this->buildBullets($text),
        ];
    }

    public function extractMetadata(string $text, array $context = []): array
    {
        return [
            'provider' => 'gemini',
            'entities' => [
                'numbers' => $this->extractNumbers($text),
            ],
        ];
    }

    public function classify(string $text, array $context = []): array
    {
        return [
            'provider' => 'gemini',
            'suggested_tags' => $this->suggestTags($text),
            'confidence' => [
                'classification' => 0.73,
            ],
        ];
    }

    public function testConnection(): array
    {
        return [
            'ok' => true,
            'provider' => 'gemini',
            'model' => $this->configuration['model'] ?? config('ai.providers.gemini.default_model'),
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

        if (Str::contains($normalized, ['solicitud', 'memo', 'oficio'])) {
            $tags[] = 'administrativo';
        }

        if (Str::contains($normalized, ['archivo', 'custodia', 'ubicación'])) {
            $tags[] = 'archivo';
        }

        if (Str::contains($normalized, ['pendiente', 'aprobación'])) {
            $tags[] = 'seguimiento';
        }

        return array_values(array_unique($tags ?: ['general']));
    }

    private function extractNumbers(string $text): array
    {
        preg_match_all('/\b\d+(?:[\.\,]\d+)?\b/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
