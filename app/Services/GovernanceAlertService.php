<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\SlaStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentArchiveClassificationMissing;
use App\Notifications\DocumentDueSoon;
use App\Notifications\DocumentOverdue;
use App\Notifications\DocumentReadyForArchive;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class GovernanceAlertService
{
    public function __construct(protected BusinessCalendarService $businessCalendarService) {}

    /**
     * @param  array<int, string>  $scopes
     * @return array<string, int>
     */
    public function processCompany(Company $company, array $scopes = ['due', 'overdue', 'archive'], bool $dryRun = false): array
    {
        $summary = [
            'due_soon' => 0,
            'overdue' => 0,
            'ready_for_archive' => 0,
            'archive_incomplete' => 0,
        ];

        if ($this->governanceFlag($company, 'send_due_soon_alerts', true) && in_array('due', $scopes, true)) {
            $summary['due_soon'] = $this->processDueSoonAlerts($company, $dryRun);
        }

        if ($this->governanceFlag($company, 'send_overdue_alerts', true) && in_array('overdue', $scopes, true)) {
            $summary['overdue'] = $this->processOverdueAlerts($company, $dryRun);
        }

        if (in_array('archive', $scopes, true)) {
            if ($this->governanceFlag($company, 'send_archive_ready_alerts', true)) {
                $summary['ready_for_archive'] = $this->processReadyForArchiveAlerts($company, $dryRun);
            }

            if ($this->governanceFlag($company, 'send_archive_incomplete_alerts', true)) {
                $summary['archive_incomplete'] = $this->processArchiveIncompleteAlerts($company, $dryRun);
            }
        }

        return $summary;
    }

    public function processDueSoonAlerts(Company $company, bool $dryRun = false): int
    {
        $documents = Document::query()
            ->with(['company', 'slaPolicy.businessCalendar', 'assignee'])
            ->where('company_id', $company->id)
            ->withActiveSla()
            ->whereIn('sla_status', [SlaStatus::Running->value, SlaStatus::Warning->value])
            ->whereNotNull('assigned_to')
            ->get();

        $sent = 0;

        foreach ($documents as $document) {
            $policy = $document->slaPolicy;
            $warningDays = collect($policy?->warning_days ?: ($this->governanceSetting($company, 'warning_days', [3, 1])))
                ->map(static fn (mixed $day): int => (int) $day)
                ->filter(static fn (int $day): bool => $day >= 0)
                ->push(0)
                ->unique()
                ->values();

            $businessDaysRemaining = $this->businessCalendarService->businessDaysUntil(
                now(),
                $document->sla_due_date,
                $company,
                $policy?->businessCalendar,
            );

            if (! $warningDays->contains($businessDaysRemaining)) {
                continue;
            }

            if ($document->assignee && ! $this->wasNotificationSentToday($document->assignee, DocumentDueSoon::class, $document->id, [
                'days_remaining' => $businessDaysRemaining,
            ])) {
                $sent += $this->send($document->assignee, new DocumentDueSoon($document, $businessDaysRemaining), $dryRun);
            }
        }

        return $sent;
    }

    public function processOverdueAlerts(Company $company, bool $dryRun = false): int
    {
        $documents = Document::query()
            ->with(['company', 'assignee', 'department', 'category', 'status', 'slaPolicy.businessCalendar'])
            ->where('company_id', $company->id)
            ->withActiveSla()
            ->where('sla_status', SlaStatus::Overdue->value)
            ->get();

        $sent = 0;

        foreach ($documents as $document) {
            $policy = $document->slaPolicy;
            $businessDaysOverdue = abs($this->businessCalendarService->businessDaysDifference(
                $document->sla_due_date,
                now(),
                $company,
                $policy?->businessCalendar,
            ));
            $hoursOverdue = max(24, $businessDaysOverdue * 24);

            if ($document->assignee && ! $this->wasNotificationSentToday($document->assignee, DocumentOverdue::class, $document->id)) {
                $sent += $this->send($document->assignee, new DocumentOverdue($document, $hoursOverdue), $dryRun);
            }

            $escalationDays = (int) ($policy?->escalation_days ?? $this->governanceSetting($company, 'escalation_days', 1));
            if ($businessDaysOverdue < max($escalationDays, 1)) {
                continue;
            }

            if (! $document->escalated_at && ! $dryRun) {
                $document->forceFill(['escalated_at' => now()])->saveQuietly();
            }

            if (! $this->governanceFlag($company, 'notify_supervisors_on_overdue', true)) {
                continue;
            }

            foreach ($this->supervisoryRecipients($document) as $recipient) {
                if (! $this->wasNotificationSentToday($recipient, DocumentOverdue::class, $document->id)) {
                    $sent += $this->send($recipient, new DocumentOverdue($document, $hoursOverdue), $dryRun);
                }
            }
        }

        return $sent;
    }

    public function processReadyForArchiveAlerts(Company $company, bool $dryRun = false): int
    {
        $documents = Document::query()
            ->with(['company'])
            ->where('company_id', $company->id)
            ->where('is_archived', false)
            ->where(function ($query) {
                $query->whereNotNull('closed_at')
                    ->orWhereNotNull('completed_at');
            })
            ->get();

        $sent = 0;

        foreach ($documents as $document) {
            foreach ($this->archiveRecipients($company) as $recipient) {
                if (! $this->wasNotificationSentToday($recipient, DocumentReadyForArchive::class, $document->id)) {
                    $sent += $this->send($recipient, new DocumentReadyForArchive($document), $dryRun);
                }
            }
        }

        return $sent;
    }

    public function processArchiveIncompleteAlerts(Company $company, bool $dryRun = false): int
    {
        $documents = Document::query()
            ->with(['company'])
            ->where('company_id', $company->id)
            ->where('is_archived', true)
            ->where(function ($query) {
                $query->whereNull('trd_series_id')
                    ->orWhereNull('trd_subseries_id')
                    ->orWhereNull('documentary_type_id')
                    ->orWhereNull('access_level');
            })
            ->get();

        $sent = 0;

        foreach ($documents as $document) {
            foreach ($this->archiveRecipients($company) as $recipient) {
                if (! $this->wasNotificationSentToday($recipient, DocumentArchiveClassificationMissing::class, $document->id)) {
                    $sent += $this->send($recipient, new DocumentArchiveClassificationMissing($document), $dryRun);
                }
            }
        }

        return $sent;
    }

    protected function governanceFlag(Company $company, string $key, bool $default): bool
    {
        return (bool) $this->governanceSetting($company, $key, $default);
    }

    protected function governanceSetting(Company $company, string $key, mixed $default = null): mixed
    {
        return data_get($company->settings, 'document_governance.'.$key, $default);
    }

    /**
     * @return Collection<int, User>
     */
    protected function archiveRecipients(Company $company): Collection
    {
        return User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [Role::ArchiveManager->value, Role::Admin->value, Role::BranchAdmin->value]))
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    protected function supervisoryRecipients(Document $document): Collection
    {
        return User::query()
            ->where('company_id', $document->company_id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                Role::Admin->value,
                Role::BranchAdmin->value,
                Role::OfficeManager->value,
            ]))
            ->get()
            ->unique('id')
            ->values();
    }

    protected function wasNotificationSentToday(User $user, string $notificationClass, int $documentId, array $constraints = []): bool
    {
        $query = $user->notifications()
            ->whereDate('created_at', now()->toDateString())
            ->where('type', $notificationClass)
            ->where('data->document_id', $documentId);

        foreach ($constraints as $key => $value) {
            $query->where('data->'.$key, $value);
        }

        return $query->exists();
    }

    protected function send(User $recipient, Notification $notification, bool $dryRun): int
    {
        if ($dryRun) {
            return 1;
        }

        $recipient->notify($notification);

        return 1;
    }
}
