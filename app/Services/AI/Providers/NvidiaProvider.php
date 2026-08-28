<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NvidiaProvider implements AiProviderContract
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
                'provider' => 'nvidia',
                'model' => $this->model(),
                'summary_md' => Str::limit(trim($text), 600),
                'executive_bullets' => $this->buildBullets($text),
            ];
        }

        $data = $this->chatJson($this->summaryPrompt($text), 2048, 0.1);

        return [
            'provider' => 'nvidia',
            'model' => $this->model(),
            'summary_md' => (string) ($data['summary_md'] ?? Str::limit(trim($text), 600)),
            'executive_bullets' => $data['executive_bullets'] ?? $this->buildBullets($text),
            'suggested_tags' => $data['suggested_tags'] ?? [],
            'entities' => $data['entities'] ?? [],
            'confidence' => $data['confidence'] ?? ['classification' => 0.80],
            ...$this->usageFromLastResponse(),
        ];
    }

    public function extractMetadata(string $text, array $context = []): array
    {
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'nvidia',
                'model' => $this->model(),
                'entities' => [
                    'numbers' => $this->extractNumbers($text),
                ],
            ];
        }

        $data = $this->chatJson($this->metadataPrompt($text), 1024, 0.0);

        return [
            'provider' => 'nvidia',
            'model' => $this->model(),
            'entities' => $data['entities'] ?? ['numbers' => $this->extractNumbers($text)],
            ...$this->usageFromLastResponse(),
        ];
    }

    public function classify(string $text, array $context = []): array
    {
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'nvidia',
                'model' => $this->model(),
                'suggested_tags' => $this->suggestTags($text),
                'confidence' => [
                    'classification' => 0.78,
                ],
            ];
        }

        $data = $this->chatJson($this->classifyPrompt($text), 1024, 0.0);

        return [
            'provider' => 'nvidia',
            'model' => $this->model(),
            'suggested_tags' => $data['suggested_tags'] ?? $this->suggestTags($text),
            'confidence' => $data['confidence'] ?? ['classification' => 0.78],
            ...$this->usageFromLastResponse(),
        ];
    }

    public function extractAccounting(string $text, array $context = []): array
    {
        if (config('ai.mock_mode')) {
            return [
                'provider' => 'nvidia',
                'model' => $this->model(),
                'fecha' => now()->toDateString(),
                'numero_documento' => 'MOCK-NVIDIA-123',
                'beneficiario' => 'MOCK NVIDIA BENEFICIARIO',
                'nit' => '900258919',
                'concepto' => 'MOCK NVIDIA CONCEPTO CONTABLE',
                'cuentas_contables' => [
                    [
                        'codigo' => '110505',
                        'descripcion' => 'Caja general',
                        'debito' => 150000,
                        'credito' => 0,
                    ],
                    [
                        'codigo' => '413595',
                        'descripcion' => 'Ingresos',
                        'debito' => 0,
                        'credito' => 150000,
                    ],
                ],
                'total' => 150000,
            ];
        }

        $data = $this->chatJson($this->accountingPrompt($text), 4096, 0.0);

        return [
            'provider' => 'nvidia',
            'model' => $this->model(),
            ...$data,
            ...$this->usageFromLastResponse(),
        ];
    }

    public function testConnection(): array
    {
        if (config('ai.mock_mode')) {
            return [
                'ok' => true,
                'provider' => 'nvidia',
                'model' => $this->model(),
                'mode' => 'mock',
            ];
        }

        $response = $this->postChat([
            'model' => $this->model(),
            'messages' => [
                ['role' => 'user', 'content' => 'Responde solo: OK'],
            ],
            'max_tokens' => 16,
            'temperature' => 0,
            'top_p' => 1,
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('La llamada a NVIDIA falló con estado: '.$response->status().' - '.$response->body());
        }

        return [
            'ok' => true,
            'provider' => 'nvidia',
            'model' => $this->model(),
            'mode' => 'live',
        ];
    }

    private array $lastUsage = [];

    private function chatJson(string $prompt, int $maxTokens, float $temperature): array
    {
        $response = $this->postChat([
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Devuelve exclusivamente JSON válido. No uses markdown, explicaciones ni bloques de código.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => 1,
            'stream' => false,
        ]);

        if ($response->failed()) {
            Log::error('nvidia.chat.failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('La llamada a NVIDIA falló con estado: '.$response->status().' - '.$response->body());
        }

        $body = $response->json();
        $this->lastUsage = $body['usage'] ?? [];
        $content = (string) data_get($body, 'choices.0.message.content', '{}');
        $decoded = json_decode($this->extractJsonObject($content), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('NVIDIA no devolvió JSON válido para la tarea solicitada.');
        }

        return $decoded;
    }

    private function postChat(array $payload): \Illuminate\Http\Client\Response
    {
        $apiKey = $this->configuration['api_key'] ?? null;

        if (! $apiKey) {
            throw new RuntimeException('API Key de NVIDIA no configurada.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('ai.timeouts.request_seconds', 30))
            ->post(rtrim((string) config('ai.providers.nvidia.base_url'), '/').'/chat/completions', $payload);
    }

    private function usageFromLastResponse(): array
    {
        return [
            'tokens_in' => $this->lastUsage['prompt_tokens'] ?? null,
            'tokens_out' => $this->lastUsage['completion_tokens'] ?? null,
            'cost_cents' => null,
        ];
    }

    private function model(): string
    {
        return $this->configuration['model'] ?? config('ai.providers.nvidia.default_model');
    }

    private function extractJsonObject(string $content): string
    {
        $trimmed = trim($content);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false || $end < $start) {
            return $trimmed;
        }

        return substr($trimmed, $start, $end - $start + 1);
    }

    private function summaryPrompt(string $text): string
    {
        return "Analiza el siguiente texto y devuelve JSON con: summary_md, executive_bullets, suggested_tags, entities y confidence.classification.\n\nTexto:\n{$text}";
    }

    private function metadataPrompt(string $text): string
    {
        return "Extrae metadatos del siguiente texto y devuelve JSON con entities.numbers, entities.dates, entities.emails y entities.amounts si existen.\n\nTexto:\n{$text}";
    }

    private function classifyPrompt(string $text): string
    {
        return "Clasifica el siguiente documento y devuelve JSON con suggested_tags y confidence.classification entre 0 y 1.\n\nTexto:\n{$text}";
    }

    private function accountingPrompt(string $text): string
    {
        return "Analiza el siguiente texto OCR de un documento de Aguas de Sucre y devuelve JSON con fecha, numero_documento, beneficiario, nit, concepto, cuentas_contables y total. Cada cuenta debe tener codigo, descripcion, debito y credito. La suma de debitos debe ser igual a la suma de creditos. Si falta información, usa null y agrega warnings.\n\nTexto:\n{$text}";
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

        if (Str::contains($normalized, ['factura', 'egreso', 'presupuesto', 'contable', 'tesorería'])) {
            $tags[] = 'finanzas';
        }

        if (Str::contains($normalized, ['archivo', 'custodia', 'ubicación'])) {
            $tags[] = 'archivo';
        }

        return array_values(array_unique($tags ?: ['general']));
    }

    private function extractNumbers(string $text): array
    {
        preg_match_all('/\b\d+(?:[\.\,]\d+)?\b/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
