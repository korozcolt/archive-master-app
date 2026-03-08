<?php

namespace App\Services;

use App\Enums\SlaStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentSlaEvent;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;

class SlaCalculatorService
{
    public function __construct(protected BusinessCalendarService $businessCalendarService) {}

    public function resolvePolicy(Company $company, ?string $pqrsType): ?SlaPolicy
    {
        if (blank($pqrsType)) {
            return null;
        }

        return $company->slaPolicies()
            ->where('code', $pqrsType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateDocumentAttributes(Document $document): array
    {
        $policy = $document->slaPolicy ?? $this->resolvePolicy($document->company, $document->pqrs_type);

        if (! $policy) {
            return [];
        }

        $startDate = CarbonImmutable::instance($document->sla_resumed_at ?? $document->received_at ?? $document->created_at ?? now());
        $dueDate = $this->businessCalendarService->addBusinessDays(
            $startDate,
            $policy->response_term_days,
            $document->company,
            $policy->businessCalendar
        )->endOfDay();

        return [
            'sla_policy_id' => $policy->id,
            'legal_basis' => $policy->legal_basis,
            'legal_term_days' => $policy->response_term_days,
            'sla_started_at' => $document->sla_started_at ?? $startDate,
            'sla_status' => $this->statusForDueDate($document, $dueDate)->value,
            'sla_due_date' => $dueDate,
            'due_date' => $dueDate,
            'due_at' => $dueDate,
        ];
    }

    public function applyToDocument(Document $document): void
    {
        $attributes = $this->calculateDocumentAttributes($document);

        if ($attributes === []) {
            return;
        }

        $document->forceFill($attributes);
    }

    public function freeze(Document $document, string $reason = 'archived'): void
    {
        $previousStatus = $document->sla_status;
        $frozenAt = $document->sla_frozen_at ?? now();

        $document->forceFill([
            'sla_status' => SlaStatus::Frozen,
            'sla_frozen_at' => $frozenAt,
            'closed_at' => $document->closed_at ?? $frozenAt,
            'first_response_at' => $document->first_response_at ?? $frozenAt,
        ]);

        if ($document->exists) {
            DocumentSlaEvent::query()->create([
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'event_type' => 'sla_frozen',
                'status_before' => $previousStatus,
                'status_after' => SlaStatus::Frozen->value,
                'occurred_at' => $frozenAt,
                'metadata' => [
                    'reason' => $reason,
                ],
            ]);
        }
    }

    public function recordStatusChange(Document $document, mixed $previousStatus, mixed $newStatus, string $eventType): void
    {
        $previousStatus = $previousStatus instanceof SlaStatus ? $previousStatus->value : $previousStatus;
        $newStatus = $newStatus instanceof SlaStatus ? $newStatus->value : $newStatus;

        if (! $document->exists || $previousStatus === $newStatus) {
            return;
        }

        DocumentSlaEvent::query()->create([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'event_type' => $eventType,
            'status_before' => $previousStatus,
            'status_after' => $newStatus,
            'occurred_at' => now(),
            'metadata' => [
                'pqrs_type' => $document->pqrs_type,
                'sla_due_date' => $document->sla_due_date?->toDateTimeString(),
            ],
        ]);
    }

    public function statusForDueDate(Document $document, CarbonImmutable $dueDate): SlaStatus
    {
        if ($document->sla_paused_at !== null) {
            return SlaStatus::Paused;
        }

        if ($document->is_archived || $document->sla_frozen_at !== null) {
            return SlaStatus::Frozen;
        }

        if ($document->closed_at !== null) {
            return SlaStatus::Closed;
        }

        if ($dueDate->isPast()) {
            return SlaStatus::Overdue;
        }

        $policy = $document->slaPolicy ?? $this->resolvePolicy($document->company, $document->pqrs_type);
        $warningDays = collect($policy?->warning_days ?? [3, 1])
            ->map(static fn (mixed $day): int => (int) $day)
            ->filter(static fn (int $day): bool => $day >= 0)
            ->sort()
            ->values();

        foreach ($warningDays as $warningDay) {
            $warningThreshold = $this->businessCalendarService->addBusinessDays(now(), $warningDay, $document->company, $policy?->businessCalendar);
            if ($dueDate->lessThanOrEqualTo($warningThreshold->endOfDay())) {
                return SlaStatus::Warning;
            }
        }

        return SlaStatus::Running;
    }
}
