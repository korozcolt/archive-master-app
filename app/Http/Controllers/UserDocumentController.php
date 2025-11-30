<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserDocumentController extends Controller
{
    /**
     * Display a listing of user's documents.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Query base de documentos del usuario
        $query = Document::where('company_id', $user->company_id)
            ->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });

        // Búsqueda por texto (título, descripción, número de documento)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por estado
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        // Filtro por prioridad
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filtro por confidencialidad
        if ($request->filled('is_confidential')) {
            $query->where('is_confidential', $request->is_confidential === '1');
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Ordenamiento
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validar campos de ordenamiento permitidos
        $allowedSorts = ['created_at', 'title', 'priority', 'updated_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest();
        }

        // Obtener documentos con relaciones
        $documents = $query->with(['status', 'category', 'creator', 'assignee'])
            ->paginate(15)
            ->withQueryString(); // Mantener parámetros de búsqueda en paginación

        // Obtener listas para filtros
        $categories = Category::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $statuses = Status::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        return view('documents.index', compact('documents', 'categories', 'statuses'));
    }

    /**
     * Show the form for creating a new document.
     */
    public function create()
    {
        $user = Auth::user();

        // Obtener categorías y estados de la empresa del usuario
        $categories = Category::where('company_id', $user->company_id)->get();
        $statuses = Status::where('company_id', $user->company_id)->get();

        return view('documents.create', compact('categories', 'statuses'));
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240', // 10MB max
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high',
        ]);

        // Procesar archivo si existe
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('documents', 'public');
        }

        // Crear documento
        $document = Document::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'created_by' => $user->id,
            'assigned_to' => $user->id, // Auto-asignar al creador
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'status_id' => $validated['status_id'],
            'is_confidential' => $request->has('is_confidential'),
            'priority' => $validated['priority'],
            'file' => $filePath,
        ]);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Documento creado exitosamente.');
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        $user = Auth::user();

        // Verificar que el usuario tenga acceso al documento
        if (!$this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para ver este documento.');
        }

        $document->load(['status', 'category', 'creator', 'assignee', 'tags', 'versions']);

        return view('documents.show', compact('document'));
    }

    /**
     * Show the form for editing the specified document.
     */
    public function edit(Document $document)
    {
        $user = Auth::user();

        // Solo el creador o el asignado pueden editar
        if (!$this->canEditDocument($user, $document)) {
            abort(403, 'No tienes permiso para editar este documento.');
        }

        $categories = Category::where('company_id', $user->company_id)->get();
        $statuses = Status::where('company_id', $user->company_id)->get();

        return view('documents.edit', compact('document', 'categories', 'statuses'));
    }

    /**
     * Update the specified document in storage.
     */
    public function update(Request $request, Document $document)
    {
        $user = Auth::user();

        if (!$this->canEditDocument($user, $document)) {
            abort(403, 'No tienes permiso para editar este documento.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high',
        ]);

        // Procesar nuevo archivo si existe
        if ($request->hasFile('file')) {
            // Eliminar archivo anterior si existe
            if ($document->file) {
                Storage::disk('public')->delete($document->file);
            }
            $validated['file'] = $request->file('file')->store('documents', 'public');
        }

        $validated['is_confidential'] = $request->has('is_confidential');

        $document->update($validated);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Documento actualizado exitosamente.');
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy(Document $document)
    {
        $user = Auth::user();

        // Solo el creador puede eliminar
        if ($document->created_by !== $user->id) {
            abort(403, 'No tienes permiso para eliminar este documento.');
        }

        // Eliminar archivo si existe
        if ($document->file) {
            Storage::disk('public')->delete($document->file);
        }

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Documento eliminado exitosamente.');
    }

    /**
     * Verificar si el usuario puede acceder al documento
     */
    private function canAccessDocument($user, $document): bool
    {
        // Mismo company
        if ($document->company_id !== $user->company_id) {
            return false;
        }

        // Es el creador, asignado, o tiene permisos especiales
        return $document->created_by === $user->id
            || $document->assigned_to === $user->id
            || $user->hasAnyRole(['admin', 'super_admin', 'branch_admin']);
    }

    /**
     * Verificar si el usuario puede editar el documento
     */
    private function canEditDocument($user, $document): bool
    {
        if ($document->company_id !== $user->company_id) {
            return false;
        }

        return $document->created_by === $user->id
            || $document->assigned_to === $user->id;
    }

    /**
     * Exportar documentos a CSV
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        // Aplicar los mismos filtros que en index()
        $query = Document::where('company_id', $user->company_id)
            ->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });

        // Aplicar filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('is_confidential')) {
            $query->where('is_confidential', $request->is_confidential === '1');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Obtener documentos para exportar
        $documents = $query->with(['status', 'category', 'creator', 'assignee'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Generar CSV
        $filename = 'documentos_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($documents) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            fputcsv($file, [
                'Número',
                'Título',
                'Descripción',
                'Categoría',
                'Estado',
                'Prioridad',
                'Confidencial',
                'Creado por',
                'Asignado a',
                'Fecha creación',
                'Última actualización'
            ]);

            // Datos
            foreach ($documents as $doc) {
                fputcsv($file, [
                    $doc->document_number,
                    $doc->title,
                    $doc->description,
                    $doc->category->name ?? '',
                    $doc->status->name ?? '',
                    $doc->priority->getLabel(),
                    $doc->is_confidential ? 'Sí' : 'No',
                    $doc->creator->name ?? '',
                    $doc->assignee->name ?? '',
                    $doc->created_at->format('d/m/Y H:i'),
                    $doc->updated_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
