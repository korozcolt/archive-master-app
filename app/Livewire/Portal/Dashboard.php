<?php

namespace App\Livewire\Portal;

use App\Enums\Role;
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
            'received' => (clone $baseQuery)->where('assigned_to', $user->id)->count(),
            'pending' => (clone $baseQuery)
                ->whereHas('status', function ($query) {
                    $query->whereIn('name', ['En Proceso', 'Pendiente']);
                })
                ->count(),
        ];

        return view('livewire.portal.dashboard', [
            'user' => $user,
            'summary' => $summary,
            'recentDocuments' => $recentDocuments,
        ])->layout('layouts.app');
    }

    private function visibleDocumentsQuery(User $user): Builder
    {
        return Document::query()
            ->where('company_id', $user->company_id)
            ->where(function (Builder $query) use ($user): void {
                $query->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);

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
