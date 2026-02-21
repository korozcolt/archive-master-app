<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Events\DocumentVersionCreated;
use App\Listeners\QueueDocumentVersionAiPipeline;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\Receipt;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Services\DocumentFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class UserDocumentController extends Controller
{
    protected DocumentFileService $documentFileService;

    public function __construct(DocumentFileService $documentFileService)
    {
        $this->documentFileService = $documentFileService;
    }

    /**
     * Display a listing of user's documents.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Query base de documentos del usuario
        $query = Document::where('company_id', $user->company_id)
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });

        // Búsqueda por texto (título, descripción, número de documento)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
        $isReceptionist = $user->roles()->where('name', Role::Receptionist->value)->exists();
        $hasReceiptData = $request->filled('recipient_name') && $request->filled('recipient_email');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50|unique:documents,document_number,NULL,id,company_id,'.$user->company_id,
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240', // 10MB max
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high',
            'recipient_name' => ($isReceptionist || $hasReceiptData) ? 'required|string|max:255' : 'nullable|string|max:255',
            'recipient_email' => ($isReceptionist || $hasReceiptData) ? 'required|email|max:255' : 'nullable|email|max:255',
            'recipient_phone' => 'nullable|string|max:30',
        ]);

        // Procesar archivo si existe
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $this->documentFileService->storeUploadedFile($request->file('file'));
        }

        // Crear documento
        $document = Document::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'created_by' => $user->id,
            'assigned_to' => $user->id, // Auto-asignar al creador
            'title' => $validated['title'],
            'document_number' => $validated['document_number'] ?? null,
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'status_id' => $validated['status_id'],
            'is_confidential' => $request->has('is_confidential'),
            'priority' => $validated['priority'],
            'file_path' => $filePath,
        ]);

        if ($isReceptionist || $hasReceiptData) {
            $receipt = $this->createReceiptForPortalUser(
                document: $document,
                issuer: $user,
                recipientName: $validated['recipient_name'],
                recipientEmail: $validated['recipient_email'],
                recipientPhone: $validated['recipient_phone'] ?? null,
            );

            return redirect()->route('documents.show', $document)
                ->with('success', 'Documento creado exitosamente. Recibido generado: '.$receipt->receipt_number);
        }

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
        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para ver este documento.');
        }

        if (function_exists('logDocumentAccess')) {
            logDocumentAccess($document, 'view');
        }

        $document->load(['status', 'category', 'creator', 'assignee', 'tags', 'versions', 'receipts', 'company']);
        $latestAiRun = $document->aiRuns()
            ->with('output')
            ->where('task', 'summarize')
            ->latest('id')
            ->first();
        $latestAiOutput = $latestAiRun?->output;

        return view('documents.show', compact('document', 'latestAiRun', 'latestAiOutput'));
    }

    /**
     * Show the form for editing the specified document.
     */
    public function edit(Document $document)
    {
        $user = Auth::user();

        // Solo el creador o el asignado pueden editar
        if (! $this->canEditDocument($user, $document)) {
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

        if (! $this->canEditDocument($user, $document)) {
            abort(403, 'No tienes permiso para editar este documento.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50|unique:documents,document_number,'.$document->id.',id,company_id,'.$user->company_id,
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high',
        ]);

        // Procesar nuevo archivo si existe
        if ($request->hasFile('file')) {
            $validated['file_path'] = $this->documentFileService->replaceFile($document->file_path, $request->file('file'));
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
        if ($document->file_path) {
            $this->documentFileService->deleteFile($document->file_path);
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

    private function createReceiptForPortalUser(
        Document $document,
        User $issuer,
        string $recipientName,
        string $recipientEmail,
        ?string $recipientPhone
    ): Receipt {
        $recipientUser = User::query()
            ->where('email', $recipientEmail)
            ->first();

        if ($recipientUser && (int) $recipientUser->company_id !== (int) $issuer->company_id) {
            abort(422, 'El correo receptor ya existe en otra empresa.');
        }

        if (! $recipientUser) {
            $recipientUser = User::create([
                'name' => $recipientName,
                'email' => $recipientEmail,
                'password' => Hash::make(Str::password(16)),
                'company_id' => $issuer->company_id,
                'branch_id' => $issuer->branch_id,
                'department_id' => $issuer->department_id,
                'position' => 'Usuario Portal',
                'phone' => $recipientPhone,
                'language' => 'es',
                'timezone' => $issuer->timezone ?? 'America/Bogota',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        SpatieRole::firstOrCreate(['name' => Role::RegularUser->value]);

        if (! $recipientUser->hasRole(Role::RegularUser->value)) {
            $recipientUser->assignRole(Role::RegularUser->value);
        }

        return Receipt::create([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'issued_by' => $issuer->id,
            'recipient_user_id' => $recipientUser->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'recipient_phone' => $recipientPhone,
            'issued_at' => now(),
        ]);
    }

    private function generateReceiptNumber(): string
    {
        do {
            $number = 'REC-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Receipt::query()->where('receipt_number', $number)->exists());

        return $number;
    }

    /**
     * Exportar documentos a CSV
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        // Aplicar los mismos filtros que en index()
        $query = Document::where('company_id', $user->company_id)
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });

        // Aplicar filtros de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
        $filename = 'documentos_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($documents) {
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
                'Última actualización',
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
                    $doc->updated_at->format('d/m/Y H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function regenerateAiSummary(Document $document)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para regenerar IA en este documento.');
        }

        if (! $user->can('create', DocumentAiRun::class)) {
            abort(403, 'No tienes permiso para ejecutar IA.');
        }

        $latestVersion = $document->versions()->latest('version_number')->first();
        if (! $latestVersion) {
            return redirect()->route('documents.show', $document)
                ->with('error', 'El documento no tiene versiones para procesar.');
        }

        $listener = app(QueueDocumentVersionAiPipeline::class);
        $beforeCount = DocumentAiRun::query()
            ->where('document_version_id', $latestVersion->id)
            ->where('task', 'summarize')
            ->count();

        $listener->handle(new DocumentVersionCreated($latestVersion));

        $afterCount = DocumentAiRun::query()
            ->where('document_version_id', $latestVersion->id)
            ->where('task', 'summarize')
            ->count();

        if ($afterCount <= $beforeCount) {
            return redirect()->route('documents.show', $document)
                ->with('warning', 'No se pudo encolar IA. Verifica configuración por compañía.');
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Resumen IA encolado para regeneración.');
    }

    public function applyAiSuggestions(Document $document)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para aplicar sugerencias IA en este documento.');
        }

        $run = $document->aiRuns()
            ->with('output')
            ->where('task', 'summarize')
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if (! $run || ! $run->output) {
            return redirect()->route('documents.show', $document)
                ->with('warning', 'No hay sugerencias IA disponibles.');
        }

        /** @var DocumentAiOutput $output */
        $output = $run->output;
        if (! $user->can('applySuggestions', $output)) {
            abort(403, 'No tienes permiso para aplicar sugerencias IA.');
        }

        $changes = 0;

        if ($output->suggested_category_id) {
            $category = Category::query()
                ->where('id', $output->suggested_category_id)
                ->where('company_id', $document->company_id)
                ->first();

            if ($category && (int) $document->category_id !== (int) $category->id) {
                $document->category_id = $category->id;
                $changes++;
            }
        }

        if ($output->suggested_department_id) {
            $department = \App\Models\Department::query()
                ->where('id', $output->suggested_department_id)
                ->where('company_id', $document->company_id)
                ->first();

            if ($department && (int) $document->department_id !== (int) $department->id) {
                $document->department_id = $department->id;
                $changes++;
            }
        }

        $tags = collect($output->suggested_tags ?? [])
            ->filter(fn ($tag): bool => is_string($tag) && trim($tag) !== '')
            ->map(fn (string $tag): string => trim($tag))
            ->unique()
            ->values();

        $tagIds = [];
        foreach ($tags as $tagName) {
            $slug = Str::slug($tagName);
            $tag = Tag::query()->firstOrCreate(
                [
                    'company_id' => $document->company_id,
                    'slug' => $slug,
                ],
                [
                    'name' => $tagName,
                    'active' => true,
                ]
            );

            $tagIds[] = $tag->id;
        }

        if ($tagIds !== []) {
            $document->tags()->syncWithoutDetaching($tagIds);
            $changes++;
        }

        if ($document->isDirty(['category_id', 'department_id'])) {
            $document->save();
        }

        return redirect()->route('documents.show', $document)
            ->with('success', $changes > 0
                ? 'Sugerencias IA aplicadas al documento.'
                : 'No hubo cambios nuevos para aplicar.');
    }

    public function markAiSummaryIncorrect(Request $request, Document $document)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para registrar feedback IA en este documento.');
        }

        $run = $document->aiRuns()
            ->with('output')
            ->where('task', 'summarize')
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if (! $run || ! $run->output) {
            return redirect()->route('documents.show', $document)
                ->with('warning', 'No hay un resumen IA para reportar.');
        }

        /** @var DocumentAiOutput $output */
        $output = $run->output;

        if (! $user->can('markIncorrect', $output)) {
            abort(403, 'No tienes permiso para reportar este resumen IA.');
        }

        $note = trim((string) $request->input('feedback_note', ''));
        $confidence = is_array($output->confidence) ? $output->confidence : [];
        $feedback = is_array($confidence['feedback'] ?? null) ? $confidence['feedback'] : [];
        $feedback[] = [
            'type' => 'incorrect',
            'user_id' => $user->id,
            'marked_at' => now()->toISOString(),
            'note' => $note !== '' ? $note : null,
        ];

        $confidence['feedback'] = $feedback;
        $output->update([
            'confidence' => $confidence,
        ]);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Feedback IA registrado. Gracias por reportarlo.');
    }
}
