<?php

namespace App\Services;

use App\Enums\ArchivePhase;
use App\Models\Document;

class ArchiveClassificationService
{
    /**
     * @return array<string, mixed>
     */
    public function calculateAttributes(Document $document): array
    {
        $classificationCode = $this->buildClassificationCode($document);
        $retentionSchedule = $document->documentaryType?->retentionSchedules()->where('is_active', true)->first()
            ?? $document->documentarySubseries?->retentionSchedules()->where('is_active', true)->first();

        return [
            'archive_classification_code' => $classificationCode,
            'access_level' => $document->access_level ?? $document->documentaryType?->access_level_default?->value,
            'retention_management_years' => $document->retention_management_years ?? $retentionSchedule?->management_years,
            'retention_central_years' => $document->retention_central_years ?? $retentionSchedule?->central_years,
            'retention_historical_action' => $document->retention_historical_action ?? $retentionSchedule?->historical_action,
            'final_disposition' => $document->final_disposition ?? $retentionSchedule?->final_disposition?->value,
            'archive_phase' => $document->archive_phase ?? ($document->is_archived ? ArchivePhase::Gestion->value : null),
        ];
    }

    public function applyToDocument(Document $document): void
    {
        $document->forceFill(array_filter(
            $this->calculateAttributes($document),
            static fn (mixed $value): bool => $value !== null,
        ));
    }

    public function buildClassificationCode(Document $document): ?string
    {
        $segments = array_filter([
            $document->documentarySeries?->code,
            $document->documentarySubseries?->code,
            $document->documentaryType?->code,
        ]);

        if ($segments === []) {
            return null;
        }

        return implode('.', $segments);
    }
}
