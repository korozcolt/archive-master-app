<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
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
        if ($user->hasAnyRole(['admin', 'super_admin', 'branch_admin', 'office_manager'])) {
            return redirect('/admin');
        }

        // Para usuarios regulares, mostrar dashboard simple
        // Query base para los documentos del usuario
        $baseQuery = Document::where('company_id', $user->company_id)
            ->where(function($query) use ($user) {
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
            ->whereHas('status', function($query) {
                $query->where('name', 'En Proceso')
                      ->orWhere('name', 'Pendiente');
            })
            ->count();

        $completedDocuments = (clone $baseQuery)
            ->whereHas('status', function($query) {
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
