<?php

namespace App\Livewire\Portal;

use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        $baseQuery = Document::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });

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
}
