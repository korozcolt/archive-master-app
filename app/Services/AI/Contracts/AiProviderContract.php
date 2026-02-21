<?php

namespace App\Services\AI\Contracts;

interface AiProviderContract
{
    public function withConfiguration(array $configuration): static;

    public function summarize(string $text, array $context = []): array;

    public function extractMetadata(string $text, array $context = []): array;

    public function classify(string $text, array $context = []): array;

    public function testConnection(): array;
}
