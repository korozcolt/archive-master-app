<?php

namespace App\Livewire\Portal;

use App\Enums\SlaStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        $baseQuery = $this->visibleDocumentsQuery($user);

        $recentDocuments = (clone $baseQuery)
            ->with([
                'status',
                'category',
                'creator',
                'assignee',
                'versions' => fn ($query) => $query->latest()->limit(1),
            ])
            ->latest()
            ->limit(5)
            ->get();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'sent' => (clone $baseQuery)->where('created_by', $user->id)->count(),
            'received' => $this->receivedDocumentsQuery($user)->count(),
            'pending' => (clone $baseQuery)
                ->whereHas('status', function ($query) {
                    $query->whereIn('name', ['En Proceso', 'Pendiente']);
                })
                ->count(),
            'warning' => (clone $baseQuery)->where('sla_status', SlaStatus::Warning->value)->count(),
            'overdue' => (clone $baseQuery)->where('sla_status', SlaStatus::Overdue->value)->count(),
            'ready_for_archive' => (clone $baseQuery)
                ->where('is_archived', false)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('completed_at')
                        ->orWhereNotNull('closed_at');
                })
                ->count(),
            'archive_pending_classification' => (clone $baseQuery)
                ->where('is_archived', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('trd_series_id')
                        ->orWhereNull('trd_subseries_id')
                        ->orWhereNull('documentary_type_id')
                        ->orWhereNull('access_level');
                })
                ->count(),
        ];

        $slaAttentionDocuments = (clone $baseQuery)
            ->with(['status', 'assignee'])
            ->whereIn('sla_status', [SlaStatus::Warning->value, SlaStatus::Overdue->value])
            ->orderByRaw("CASE WHEN sla_status = 'overdue' THEN 0 ELSE 1 END")
            ->orderBy('sla_due_date')
            ->limit(5)
            ->get();

        return view('livewire.portal.dashboard', [
            'user' => $user,
            'summary' => $summary,
            'recentDocuments' => $recentDocuments,
            'slaAttentionDocuments' => $slaAttentionDocuments,
        ])->layout('layouts.app');
    }

    private function visibleDocumentsQuery(User $user): Builder
    {
        return Document::query()->visibleToPortalUser($user);
    }

    private function receivedDocumentsQuery(User $user): Builder
    {
        $query = Document::query()->where('company_id', $user->company_id);

        if ($user->hasRole(\App\Enums\Role::RegularUser->value)) {
            return $query->whereHas('receipts', function (Builder $receiptQuery) use ($user): void {
                $receiptQuery->where('recipient_user_id', $user->id);
            });
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('assigned_to', $user->id);

            if (
                $user->department_id &&
                $user->hasAnyRole([\App\Enums\Role::OfficeManager->value, \App\Enums\Role::ArchiveManager->value])
            ) {
                $builder->orWhereHas('distributions.targets', function (Builder $targetQuery) use ($user): void {
                    $targetQuery->where('department_id', $user->department_id);
                });
            }
        });
    }
}
