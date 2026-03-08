<?php

namespace App\Livewire\Portal;

use App\Enums\Role;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Reports extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function render(): View
    {
        $user = Auth::user();

        $sentQuery = $this->baseQuery($user)->where('created_by', $user->id);
        $receivedQuery = $this->receivedQuery($user);

        $summary = [
            'sent' => (clone $sentQuery)->count(),
            'received' => (clone $receivedQuery)->count(),
        ];

        $sentDocuments = (clone $sentQuery)
            ->with(['status', 'category', 'creator', 'assignee'])
            ->latest()
            ->limit(20)
            ->get();

        $receivedDocuments = (clone $receivedQuery)
            ->with(['status', 'category', 'creator', 'assignee'])
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.portal.reports', [
            'summary' => $summary,
            'sentDocuments' => $sentDocuments,
            'receivedDocuments' => $receivedDocuments,
        ])->layout('layouts.app');
    }

    private function baseQuery(User $user): Builder
    {
        $query = Document::query()
            ->where('company_id', $user->company_id);

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query;
    }

    private function receivedQuery(User $user): Builder
    {
        if ($user->hasRole(Role::RegularUser->value)) {
            return $this->baseQuery($user)
                ->whereHas('receipts', function (Builder $receiptQuery) use ($user): void {
                    $receiptQuery->where('recipient_user_id', $user->id);
                });
        }

        return $this->baseQuery($user)
            ->where(function (Builder $query) use ($user): void {
                $query->where('assigned_to', $user->id);

                if (
                    $user->department_id &&
                    $user->hasAnyRole([Role::OfficeManager->value, Role::ArchiveManager->value])
                ) {
                    $query->orWhereHas('distributions.targets', function (Builder $targetQuery) use ($user): void {
                        $targetQuery->where('department_id', $user->department_id);
                    });
                }
            });
    }
}
