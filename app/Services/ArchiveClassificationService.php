<?php

namespace App\Services;

use App\Enums\ArchivePhase;
use App\Models\Document;
use App\Models\RetentionSchedule;
use Illuminate\Database\Eloquent\Builder;

class ArchiveClassificationService
{
    /**
     * @return array<string, mixed>
     */
    public function calculateAttributes(Document $document): array
    {
        $classificationCode = $this->buildClassificationCode($document);
        $retentionSchedule = $this->findRetentionSchedule($document);

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

    private function findRetentionSchedule(Document $document): ?RetentionSchedule
    {
        if ($document->documentary_type_id) {
            $schedule = $this->retentionScheduleQuery($document)
                ->where('documentary_type_id', $document->documentary_type_id)
                ->first();

            if ($schedule) {
                return $schedule;
            }
        }

        if (! $document->trd_subseries_id) {
            return null;
        }

        return $this->retentionScheduleQuery($document)
            ->whereNull('documentary_type_id')
            ->where('documentary_subseries_id', $document->trd_subseries_id)
            ->first();
    }

    private function retentionScheduleQuery(Document $document): Builder
    {
        return RetentionSchedule::query()
            ->where('company_id', $document->company_id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($document): void {
                if ($document->department_id) {
                    $query->where('department_id', $document->department_id)
                        ->orWhereNull('department_id');
                } else {
                    $query->whereNull('department_id');
                }
            })
            ->orderByRaw('department_id is null');
    }
}
