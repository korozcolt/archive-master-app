<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * Muestra un dashboard personalizado según el rol del usuario
     */
    public function index()
    {
        $user = Auth::user();

        // Si es administrador, redirigir al panel de Filament
        if ($user->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value, Role::BranchAdmin->value])) {
            return redirect('/admin');
        }

        // Para usuarios de portal, redirigir al portal
        if ($user->hasAnyRole([
            Role::OfficeManager->value,
            Role::ArchiveManager->value,
            Role::Receptionist->value,
            Role::RegularUser->value,
        ])) {
            return redirect('/portal');
        }

        // Para usuarios sin rol, mostrar dashboard simple
        // Query base para los documentos del usuario
        $baseQuery = Document::where('company_id', $user->company_id)
            ->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });

        // Documentos recientes
        $myDocuments = (clone $baseQuery)
            ->with(['status', 'category', 'creator'])
            ->latest()
            ->limit(5)
            ->get();

        // Estadísticas
        $totalDocuments = (clone $baseQuery)->count();

        $pendingDocuments = (clone $baseQuery)
            ->whereHas('status', function ($query) {
                $query->where('name', 'En Proceso')
                    ->orWhere('name', 'Pendiente');
            })
            ->count();

        $completedDocuments = (clone $baseQuery)
            ->whereHas('status', function ($query) {
                $query->where('name', 'Completado');
            })
            ->count();

        $highPriorityDocuments = (clone $baseQuery)
            ->where('priority', 'high')
            ->count();

        $confidentialDocuments = (clone $baseQuery)
            ->where('is_confidential', true)
            ->count();

        return view('dashboard', compact(
            'user',
            'myDocuments',
            'totalDocuments',
            'pendingDocuments',
            'completedDocuments',
            'highPriorityDocuments',
            'confidentialDocuments'
        ));
    }
}
