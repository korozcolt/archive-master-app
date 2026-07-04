<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'gemini',
                'model' => $this->configuration['model'] ?? config('ai.providers.gemini.default_model'),
                'summary_md' => Str::limit(trim($text), 600),
                'executive_bullets' => $this->buildBullets($text),
            ];
        }

        $apiKey = $this->configuration['api_key'] ?? null;
        $model = $this->configuration['model'] ?? config('ai.providers.gemini.default_model');

        if (! $apiKey) {
            throw new \RuntimeException('API Key de Gemini no configurada.');
        }

        $prompt = "Analiza el siguiente texto y devuelve un objeto JSON estructurado con los siguientes campos estrictamente en español:
1. 'summary_md': Un resumen ejecutivo conciso del documento en formato Markdown (máximo 600 caracteres).
2. 'executive_bullets': Una lista (array de strings) con las 3 a 5 ideas clave más importantes.
3. 'suggested_tags': Un array de strings con etiquetas/palabras clave adecuadas para clasificar el documento (ej. 'administrativo', 'finanzas', 'contrato', 'oficio', 'urgente', etc.).
4. 'entities': Un objeto JSON con arreglos para 'dates' (fechas en formato DD/MM/AAAA encontradas), 'emails' (correos electrónicos encontrados), y 'numbers' (números de identificación, montos o códigos relevantes).
5. 'confidence': Un objeto con el campo 'classification' que sea un número flotante entre 0.0 y 1.0 que indique el nivel de confianza de la clasificación.

Texto a analizar:
{$text}";

        try {
            $response = Http::timeout(config('ai.timeouts.request_seconds', 30))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('La llamada a la API de Gemini falló con estado: '.$response->status().' - '.$response->body());
            }

            $body = $response->json();
            $textResult = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $data = json_decode($textResult, true) ?: [];

            $tokensIn = $body['usageMetadata']['promptTokenCount'] ?? null;
            $tokensOut = $body['usageMetadata']['candidatesTokenCount'] ?? null;

            $costCents = null;
            if ($tokensIn !== null && $tokensOut !== null) {
                $costCents = (($tokensIn * 0.0000075) + ($tokensOut * 0.00003)) * 100;
                $costCents = max(1, (int) round($costCents));
            }

            return [
                'provider' => 'gemini',
                'model' => $model,
                'summary_md' => $data['summary_md'] ?? Str::limit(trim($text), 600),
                'executive_bullets' => $data['executive_bullets'] ?? $this->buildBullets($text),
                'suggested_tags' => $data['suggested_tags'] ?? [],
                'entities' => $data['entities'] ?? [],
                'confidence' => $data['confidence'] ?? ['classification' => 0.90],
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost_cents' => $costCents,
            ];
        } catch (\Throwable $e) {
            Log::error('gemini.summarize.failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Error de Gemini durante la generación de resumen: '.$e->getMessage());
        }
    }

    public function extractMetadata(string $text, array $context = []): array
    {
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'gemini',
                'entities' => [
                    'numbers' => $this->extractNumbers($text),
                ],
            ];
        }

        $apiKey = $this->configuration['api_key'] ?? null;
        $model = $this->configuration['model'] ?? config('ai.providers.gemini.default_model');

        if (! $apiKey) {
            throw new \RuntimeException('API Key de Gemini no configurada.');
        }

        $prompt = "Analiza el siguiente texto y devuelve un objeto JSON estructurado con el siguiente campo:
1. 'entities': Un objeto JSON con el arreglo 'numbers' (números de identificación, montos o códigos relevantes).

Texto a analizar:
{$text}";

        try {
            $response = Http::timeout(config('ai.timeouts.request_seconds', 30))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('La llamada a la API de Gemini falló con estado: '.$response->status().' - '.$response->body());
            }

            $body = $response->json();
            $textResult = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $data = json_decode($textResult, true) ?: [];

            $tokensIn = $body['usageMetadata']['promptTokenCount'] ?? null;
            $tokensOut = $body['usageMetadata']['candidatesTokenCount'] ?? null;

            $costCents = null;
            if ($tokensIn !== null && $tokensOut !== null) {
                $costCents = (($tokensIn * 0.0000075) + ($tokensOut * 0.00003)) * 100;
                $costCents = max(1, (int) round($costCents));
            }

            return [
                'provider' => 'gemini',
                'entities' => $data['entities'] ?? [
                    'numbers' => $this->extractNumbers($text),
                ],
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost_cents' => $costCents,
            ];
        } catch (\Throwable $e) {
            Log::error('gemini.extractMetadata.failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Error de Gemini durante la extracción de metadatos: '.$e->getMessage());
        }
    }

    public function classify(string $text, array $context = []): array
    {
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'gemini',
                'suggested_tags' => $this->suggestTags($text),
                'confidence' => [
                    'classification' => 0.73,
                ],
            ];
        }

        $apiKey = $this->configuration['api_key'] ?? null;
        $model = $this->configuration['model'] ?? config('ai.providers.gemini.default_model');

        if (! $apiKey) {
            throw new \RuntimeException('API Key de Gemini no configurada.');
        }

        $prompt = "Analiza el siguiente texto y devuelve un objeto JSON estructurado con los siguientes campos:
1. 'suggested_tags': Un array de strings con etiquetas/palabras clave adecuadas para clasificar el documento (ej. 'administrativo', 'finanzas', 'contrato', 'oficio', 'urgente', etc.).
2. 'confidence': Un objeto con el campo 'classification' que sea un número flotante entre 0.0 y 1.0 que indique el nivel de confianza de la clasificación.

Texto a analizar:
{$text}";

        try {
            $response = Http::timeout(config('ai.timeouts.request_seconds', 30))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('La llamada a la API de Gemini falló con estado: '.$response->status().' - '.$response->body());
            }

            $body = $response->json();
            $textResult = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $data = json_decode($textResult, true) ?: [];

            $tokensIn = $body['usageMetadata']['promptTokenCount'] ?? null;
            $tokensOut = $body['usageMetadata']['candidatesTokenCount'] ?? null;

            $costCents = null;
            if ($tokensIn !== null && $tokensOut !== null) {
                $costCents = (($tokensIn * 0.0000075) + ($tokensOut * 0.00003)) * 100;
                $costCents = max(1, (int) round($costCents));
            }

            return [
                'provider' => 'gemini',
                'suggested_tags' => $data['suggested_tags'] ?? $this->suggestTags($text),
                'confidence' => $data['confidence'] ?? [
                    'classification' => 0.73,
                ],
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost_cents' => $costCents,
            ];
        } catch (\Throwable $e) {
            Log::error('gemini.classify.failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Error de Gemini durante la clasificación: '.$e->getMessage());
        }
    }

    public function testConnection(): array
    {
        if (config('ai.mock_mode')) {
            return [
                'ok' => true,
                'provider' => 'gemini',
                'model' => $this->configuration['model'] ?? config('ai.providers.gemini.default_model'),
                'mode' => 'mock',
            ];
        }

        $apiKey = $this->configuration['api_key'] ?? null;
        $model = $this->configuration['model'] ?? config('ai.providers.gemini.default_model');

        if (! $apiKey) {
            throw new \RuntimeException('API Key de Gemini no configurada.');
        }

        try {
            $response = Http::timeout(config('ai.timeouts.request_seconds', 30))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => 'Ping'],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 5,
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('La llamada a la API de Gemini falló con estado: '.$response->status().' - '.$response->body());
            }

            return [
                'ok' => true,
                'provider' => 'gemini',
                'model' => $model,
                'mode' => 'live',
            ];
        } catch (\Throwable $e) {
            Log::error('gemini.connection.failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Error conectando con la API de Gemini: '.$e->getMessage());
        }
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
