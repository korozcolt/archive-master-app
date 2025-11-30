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
    public function index()
    {
        $user = Auth::user();

        // Obtener documentos del usuario (asignados o creados por él)
        $documents = Document::where('company_id', $user->company_id)
            ->where(function($query) use ($user) {
                $query->where('assigned_to', $user->id)
                      ->orWhere('created_by', $user->id);
            })
            ->with(['status', 'category', 'creator', 'assignee'])
            ->latest()
            ->paginate(15);

        return view('documents.index', compact('documents'));
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
}
