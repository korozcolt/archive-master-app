<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Status;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions';
    
    protected static ?string $heading = 'Accesos Rápidos';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    public function getViewData(): array
    {
        $user = Auth::user();
        
        return [
            'pendingDocuments' => $this->getPendingDocuments($user),
            'recentDocuments' => $this->getRecentDocuments($user),
        ];
    }
    
    private function getPendingDocuments($user)
    {
        return Document::where('assigned_to', $user->id)
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->with(['status', 'category', 'creator'])
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'number' => $document->document_number,
                    'title' => $document->title,
                    'status' => $document->status->name,
                    'status_color' => $this->getStatusColor($document->status),
                    'category' => $document->category->name ?? 'Sin categoría',
                    'creator' => $document->creator->name,
                    'created_at' => $document->created_at->diffForHumans(),
                    'is_overdue' => $document->created_at->addDays(7)->isPast(),
                    'url' => route('filament.admin.resources.documents.edit', $document),
                ];
            });
    }
    
    private function getRecentDocuments($user)
    {
        return Document::where('company_id', $user->company_id)
            ->with(['status', 'category', 'assignee'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'number' => $document->document_number,
                    'title' => $document->title,
                    'status' => $document->status->name,
                    'status_color' => $this->getStatusColor($document->status),
                    'category' => $document->category->name ?? 'Sin categoría',
                    'assignee' => $document->assignee->name ?? 'Sin asignar',
                    'updated_at' => $document->updated_at->diffForHumans(),
                    'url' => route('filament.admin.resources.documents.view', $document),
                ];
            });
    }
    
    private function getStatusColor($status)
    {
        $statusName = strtolower($status->name);
        
        if (str_contains($statusName, 'pendiente') || str_contains($statusName, 'pending')) {
            return 'warning';
        }
        
        if (str_contains($statusName, 'aprobado') || str_contains($statusName, 'approved') || str_contains($statusName, 'completado')) {
            return 'success';
        }
        
        if (str_contains($statusName, 'rechazado') || str_contains($statusName, 'rejected')) {
            return 'danger';
        }
        
        if (str_contains($statusName, 'proceso') || str_contains($statusName, 'progress')) {
            return 'info';
        }
        
        return 'gray';
    }
}