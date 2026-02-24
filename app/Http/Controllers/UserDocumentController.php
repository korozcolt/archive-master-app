<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\Role;
use App\Events\DocumentVersionCreated;
use App\Listeners\QueueDocumentVersionAiPipeline;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentDistribution;
use App\Models\DocumentDistributionTarget;
use App\Models\DocumentUploadDraft;
use App\Models\DocumentUploadDraftItem;
use App\Models\PhysicalLocation;
use App\Models\Receipt;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\DocumentDistributedToOfficeNotification;
use App\Notifications\DocumentDistributionTargetUpdatedNotification;
use App\Services\DocumentFileService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
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

                if ($user->hasRole(Role::OfficeManager->value) && $user->department_id) {
                    $q->orWhereHas('distributions.targets', function ($targetQuery) use ($user) {
                        $targetQuery->where('department_id', $user->department_id);
                    });
                }

                if ($user->hasRole(Role::ArchiveManager->value)) {
                    $q->orWhere('company_id', $user->company_id);
                }
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
        $documents = $query->with([
            'status',
            'category',
            'creator',
            'assignee',
            'versions' => fn ($versionQuery) => $versionQuery
                ->orderByDesc('id')
                ->limit(1),
        ])
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

        $draftId = request()->integer('draft');
        $uploadDraft = null;
        $uploadDraftPayload = null;

        if ($draftId) {
            $uploadDraft = $this->findOwnedUploadDraft($user, $draftId);

            if ($uploadDraft && $uploadDraft->status === 'draft') {
                $uploadDraft->load('items');
                $uploadDraftPayload = $this->serializeUploadDraftForView($uploadDraft);
            }
        }

        return view('documents.create', compact('categories', 'statuses', 'uploadDraft', 'uploadDraftPayload'));
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $isReceptionist = $user->roles()->where('name', Role::Receptionist->value)->exists();
        $hasReceiptData = $request->filled('recipient_name') && $request->filled('recipient_email');

        if ($request->filled('draft_id')) {
            return $this->storeDocumentsFromDraft($request, $user, $isReceptionist, $hasReceiptData);
        }

        $bulkFiles = $request->allFiles()['files'] ?? [];
        $hasBulkFiles = is_array($bulkFiles) && count(array_filter($bulkFiles)) > 0;

        if ($hasBulkFiles || $request->has('bulk_items')) {
            return $this->storeBulkDocuments($request, $user, $isReceptionist, $hasReceiptData);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50|unique:documents,document_number,NULL,id,company_id,'.$user->company_id,
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
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

    public function uploadDraftTempFile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'draft_id' => 'nullable|integer',
        ]);

        $draft = null;
        if (! empty($validated['draft_id'])) {
            $draft = $this->findOwnedUploadDraft($user, (int) $validated['draft_id']);
        }

        if (! $draft) {
            $draft = DocumentUploadDraft::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'department_id' => $user->department_id,
                'status' => 'draft',
                'priority' => 'medium',
                'current_step' => 1,
            ]);
        }

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $validated['file'];
        $stored = $this->documentFileService->storeTemporaryUploadedFile($uploadedFile, $user->id);

        $nextOrder = ((int) $draft->items()->max('sort_order')) + 1;
        $item = $draft->items()->create([
            'sort_order' => $nextOrder,
            'original_name' => $stored['original_name'],
            'stored_name' => basename($stored['path']),
            'temp_disk' => $stored['disk'],
            'temp_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'title' => $this->makeTitleFromFilename($uploadedFile),
        ]);

        return response()->json([
            'draft_id' => $draft->id,
            'item' => $this->serializeUploadDraftItem($item),
        ]);
    }

    public function saveUploadDraft(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'draft_id' => 'nullable|integer',
            'current_step' => 'nullable|integer|min:1|max:4',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status_id' => 'nullable|exists:statuses,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'is_confidential' => 'nullable|boolean',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'recipient_phone' => 'nullable|string|max:30',
            'items' => 'nullable|array',
            'items.*.id' => 'required|integer',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.status_id' => 'nullable|exists:statuses,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $draft = null;
        if (! empty($validated['draft_id'])) {
            $draft = $this->findOwnedUploadDraft($user, (int) $validated['draft_id']);
        }

        if (! $draft) {
            $draft = DocumentUploadDraft::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'department_id' => $user->department_id,
                'status' => 'draft',
                'priority' => 'medium',
                'current_step' => 1,
            ]);
        }

        $draft->fill([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'status_id' => $validated['status_id'] ?? null,
            'priority' => $validated['priority'] ?? $draft->priority,
            'is_confidential' => (bool) ($validated['is_confidential'] ?? false),
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'current_step' => $validated['current_step'] ?? $draft->current_step,
            'status' => 'draft',
        ]);
        $draft->save();

        $itemsPayload = collect($validated['items'] ?? []);
        if ($itemsPayload->isNotEmpty()) {
            $draftItems = $draft->items()->get()->keyBy('id');
            foreach ($itemsPayload as $row) {
                $item = $draftItems->get((int) $row['id']);
                if (! $item) {
                    continue;
                }

                $item->fill([
                    'title' => $row['title'] ?? $item->title,
                    'category_id' => $row['category_id'] ?? null,
                    'status_id' => $row['status_id'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $item->sort_order,
                ]);
                $item->save();
            }
        }

        $draft->load('items');

        return response()->json([
            'draft_id' => $draft->id,
            'draft' => $this->serializeUploadDraftForView($draft),
            'message' => 'Borrador guardado.',
        ]);
    }

    public function deleteUploadDraftItem(Request $request, int $draft, int $item)
    {
        $user = Auth::user();
        $uploadDraft = $this->findOwnedUploadDraft($user, $draft);

        abort_unless($uploadDraft !== null, 404);

        $draftItem = $uploadDraft->items()->whereKey($item)->firstOrFail();
        if ($draftItem->temp_path) {
            \Illuminate\Support\Facades\Storage::disk($draftItem->temp_disk ?: 'local')->delete($draftItem->temp_path);
        }
        $draftItem->delete();

        return response()->json(['ok' => true]);
    }

    private function storeBulkDocuments(Request $request, User $user, bool $isReceptionist, bool $hasReceiptData)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high',
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'bulk_items' => 'nullable|array',
            'bulk_items.*.title' => 'nullable|string|max:255',
            'bulk_items.*.category_id' => 'nullable|exists:categories,id',
            'bulk_items.*.status_id' => 'nullable|exists:statuses,id',
            'recipient_name' => ($isReceptionist || $hasReceiptData) ? 'required|string|max:255' : 'nullable|string|max:255',
            'recipient_email' => ($isReceptionist || $hasReceiptData) ? 'required|email|max:255' : 'nullable|email|max:255',
            'recipient_phone' => 'nullable|string|max:30',
        ]);

        $bulkItems = collect($request->input('bulk_items', []));
        $files = $request->file('files', []);

        $createdDocuments = [];
        $createdReceipts = [];

        DB::transaction(function () use (
            $files,
            $bulkItems,
            $validated,
            $request,
            $user,
            $isReceptionist,
            $hasReceiptData,
            &$createdDocuments,
            &$createdReceipts
        ): void {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $row = $bulkItems->get($index, []);
                $rowTitle = is_array($row) ? trim((string) ($row['title'] ?? '')) : '';
                $rowCategoryId = is_array($row) && filled($row['category_id'] ?? null)
                    ? (int) $row['category_id']
                    : (int) $validated['category_id'];
                $rowStatusId = is_array($row) && filled($row['status_id'] ?? null)
                    ? (int) $row['status_id']
                    : (int) $validated['status_id'];

                $document = Document::create([
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $user->department_id,
                    'created_by' => $user->id,
                    'assigned_to' => $user->id,
                    'title' => $rowTitle !== '' ? $rowTitle : $this->makeTitleFromFilename($file),
                    'document_number' => null,
                    'description' => $validated['description'] ?? null,
                    'category_id' => $rowCategoryId,
                    'status_id' => $rowStatusId,
                    'is_confidential' => $request->boolean('is_confidential'),
                    'priority' => $validated['priority'],
                    'file_path' => $this->documentFileService->storeUploadedFile($file),
                ]);

                $createdDocuments[] = $document;

                if ($isReceptionist || $hasReceiptData) {
                    $createdReceipts[] = $this->createReceiptForPortalUser(
                        document: $document,
                        issuer: $user,
                        recipientName: $validated['recipient_name'],
                        recipientEmail: $validated['recipient_email'],
                        recipientPhone: $validated['recipient_phone'] ?? null,
                    );
                }
            }
        });

        $countDocuments = count($createdDocuments);
        $countReceipts = count($createdReceipts);

        if ($countDocuments === 1) {
            $document = $createdDocuments[0];

            if ($countReceipts === 1) {
                return redirect()->route('documents.show', $document)
                    ->with('success', 'Documento creado exitosamente. Recibido generado: '.$createdReceipts[0]->receipt_number);
            }

            return redirect()->route('documents.show', $document)
                ->with('success', 'Documento creado exitosamente.');
        }

        $message = "{$countDocuments} documentos creados exitosamente.";

        if ($countReceipts > 0) {
            $message .= " {$countReceipts} recibidos generados.";
        }

        return redirect()->route('documents.index')->with('success', $message);
    }

    private function storeDocumentsFromDraft(Request $request, User $user, bool $isReceptionist, bool $hasReceiptData)
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'is_confidential' => 'boolean',
            'priority' => 'required|in:low,medium,high,urgent',
            'bulk_items' => 'nullable|array',
            'bulk_items.*.id' => 'nullable|integer',
            'bulk_items.*.title' => 'nullable|string|max:255',
            'bulk_items.*.category_id' => 'nullable|exists:categories,id',
            'bulk_items.*.status_id' => 'nullable|exists:statuses,id',
            'recipient_name' => ($isReceptionist || $hasReceiptData) ? 'required|string|max:255' : 'nullable|string|max:255',
            'recipient_email' => ($isReceptionist || $hasReceiptData) ? 'required|email|max:255' : 'nullable|email|max:255',
            'recipient_phone' => 'nullable|string|max:30',
        ]);

        $draft = $this->findOwnedUploadDraft($user, (int) $validated['draft_id']);
        if (! $draft || $draft->status !== 'draft') {
            return back()->withErrors(['draft_id' => 'El borrador no existe o ya fue procesado.']);
        }

        $draft->load('items');
        if ($draft->items->isEmpty()) {
            return back()->withErrors(['draft_id' => 'El borrador no tiene archivos cargados.']);
        }

        $submittedItems = collect($validated['bulk_items'] ?? [])->keyBy(fn ($row, $index) => (string) ($row['id'] ?? $index));
        if ($submittedItems->isNotEmpty()) {
            foreach ($draft->items as $index => $draftItem) {
                $payload = $submittedItems->get((string) $draftItem->id) ?? $submittedItems->get((string) $index);
                if (! is_array($payload)) {
                    continue;
                }

                $draftItem->fill([
                    'title' => trim((string) ($payload['title'] ?? $draftItem->title)) ?: $draftItem->title,
                    'category_id' => filled($payload['category_id'] ?? null) ? (int) $payload['category_id'] : null,
                    'status_id' => filled($payload['status_id'] ?? null) ? (int) $payload['status_id'] : null,
                ])->save();
            }

            $draft->refresh()->load('items');
        }

        $draft->fill([
            'description' => $validated['description'] ?? null,
            'category_id' => (int) $validated['category_id'],
            'status_id' => (int) $validated['status_id'],
            'priority' => $validated['priority'],
            'is_confidential' => $request->boolean('is_confidential'),
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'current_step' => 4,
        ])->save();

        $createdDocuments = [];
        $createdReceipts = [];

        DB::transaction(function () use ($draft, $user, $validated, $request, $isReceptionist, $hasReceiptData, &$createdDocuments, &$createdReceipts): void {
            foreach ($draft->items as $item) {
                $filePath = $this->documentFileService->promoteTemporaryFile(
                    tempPath: $item->temp_path,
                    tempDisk: $item->temp_disk,
                    preferredFilename: $item->original_name,
                );

                $document = Document::create([
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $user->department_id,
                    'created_by' => $user->id,
                    'assigned_to' => $user->id,
                    'title' => $item->title ?: $this->makeTitleFromFilename($item->original_name),
                    'document_number' => null,
                    'description' => $validated['description'] ?? null,
                    'category_id' => $item->category_id ?: (int) $validated['category_id'],
                    'status_id' => $item->status_id ?: (int) $validated['status_id'],
                    'is_confidential' => $request->boolean('is_confidential'),
                    'priority' => $validated['priority'],
                    'file_path' => $filePath,
                ]);

                $createdDocuments[] = $document;

                if ($isReceptionist || $hasReceiptData) {
                    $createdReceipts[] = $this->createReceiptForPortalUser(
                        document: $document,
                        issuer: $user,
                        recipientName: $validated['recipient_name'],
                        recipientEmail: $validated['recipient_email'],
                        recipientPhone: $validated['recipient_phone'] ?? null,
                    );
                }
            }

            $draft->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        });

        $countDocuments = count($createdDocuments);
        $countReceipts = count($createdReceipts);

        if ($countDocuments === 1) {
            $document = $createdDocuments[0];

            if ($countReceipts === 1) {
                return redirect()->route('documents.show', $document)
                    ->with('success', 'Documento creado exitosamente. Recibido generado: '.$createdReceipts[0]->receipt_number);
            }

            return redirect()->route('documents.show', $document)
                ->with('success', 'Documento creado exitosamente.');
        }

        $message = "{$countDocuments} documentos creados exitosamente.";
        if ($countReceipts > 0) {
            $message .= " {$countReceipts} recibidos generados.";
        }

        return redirect()->route('documents.index')->with('success', $message);
    }

    private function findOwnedUploadDraft(User $user, int $draftId): ?DocumentUploadDraft
    {
        return DocumentUploadDraft::query()
            ->whereKey($draftId)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->first();
    }

    private function serializeUploadDraftForView(DocumentUploadDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'current_step' => (int) ($draft->current_step ?: 1),
            'title' => $draft->title,
            'description' => $draft->description,
            'category_id' => $draft->category_id,
            'status_id' => $draft->status_id,
            'priority' => $draft->priority,
            'is_confidential' => (bool) $draft->is_confidential,
            'recipient_name' => $draft->recipient_name,
            'recipient_email' => $draft->recipient_email,
            'recipient_phone' => $draft->recipient_phone,
            'items' => $draft->items->map(fn (DocumentUploadDraftItem $item): array => $this->serializeUploadDraftItem($item))->values()->all(),
        ];
    }

    private function serializeUploadDraftItem(DocumentUploadDraftItem $item): array
    {
        $extension = pathinfo((string) $item->original_name, PATHINFO_EXTENSION);

        return [
            'id' => $item->id,
            'uid' => 'draft-item-'.$item->id,
            'fileName' => $item->original_name,
            'title' => $item->title,
            'category_id' => $item->category_id ? (string) $item->category_id : '',
            'status_id' => $item->status_id ? (string) $item->status_id : '',
            'extension' => mb_strtolower((string) $extension),
            'sizeLabel' => $this->formatBytesLabel((int) $item->size_bytes),
        ];
    }

    private function formatBytesLabel(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < (1024 * 1024)) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
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

        $document->load(['status', 'category', 'creator', 'assignee', 'tags', 'versions', 'receipts', 'company', 'aiRuns.output', 'physicalLocation']);
        $latestAiRun = $document->aiRuns()
            ->with('output')
            ->where('task', 'summarize')
            ->latest('id')
            ->first();
        $latestAiOutput = $latestAiRun?->output;
        $activityLog = Activity::query()
            ->where('subject_type', Document::class)
            ->where('subject_id', $document->id)
            ->with('causer')
            ->latest('created_at')
            ->limit(30)
            ->get();

        $document->load([
            'distributions.creator',
            'distributions.targets.department',
            'distributions.targets.assignedUser',
            'distributions.targets.lastUpdatedBy',
            'distributions.targets.respondedBy',
            'distributions.targets.responseDocument',
            'locationHistory.physicalLocation',
            'locationHistory.movedFromLocation',
            'locationHistory.movedBy',
        ]);

        $distributionTargets = $document->distributions
            ->flatMap(fn (DocumentDistribution $distribution) => $distribution->targets->map(function (DocumentDistributionTarget $target) use ($distribution) {
                $target->setRelation('distribution', $distribution);

                return $target;
            }))
            ->sortByDesc(fn (DocumentDistributionTarget $target) => $target->last_activity_at ?? $target->updated_at)
            ->values();

        $distributedDepartmentIds = $distributionTargets
            ->pluck('department_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $distributionDepartmentOptions = collect();
        if (Auth::user()?->hasRole(Role::Receptionist->value)) {
            $distributionDepartmentOptions = Department::query()
                ->where('company_id', $document->company_id)
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->map(function (Department $department) use ($distributedDepartmentIds): Department {
                    $department->setAttribute('already_distributed', in_array((int) $department->id, $distributedDepartmentIds, true));

                    return $department;
                });
        }

        $distributionResponseDocumentOptions = collect();
        if ($user->hasRole(Role::OfficeManager->value) && $user->department_id) {
            $distributionResponseDocumentOptions = Document::query()
                ->where('company_id', $document->company_id)
                ->where('department_id', $user->department_id)
                ->where('id', '!=', $document->id)
                ->where(function ($query) use ($user): void {
                    $query->where('created_by', $user->id)
                        ->orWhere('assigned_to', $user->id);
                })
                ->latest('id')
                ->limit(50)
                ->get(['id', 'title', 'document_number'])
                ->mapWithKeys(fn (Document $candidate): array => [
                    $candidate->id => trim(($candidate->document_number ? "[{$candidate->document_number}] " : '').$candidate->title),
                ]);
        }

        $archiveLocationOptions = collect();
        if ($user->hasRole(Role::ArchiveManager->value) || $user->hasAnyRole(['admin', 'super_admin', 'branch_admin'])) {
            $archiveLocationOptions = PhysicalLocation::query()
                ->where('company_id', $document->company_id)
                ->where(function ($query) use ($document): void {
                    $query->where('is_active', true);

                    if ($document->physical_location_id) {
                        $query->orWhere('id', $document->physical_location_id);
                    }
                })
                ->orderBy('full_path')
                ->get();
        }

        $documentLocationHistory = $document->locationHistory
            ->sortByDesc(fn ($history) => $history->moved_at ?? $history->created_at)
            ->take(5)
            ->values();

        $activityUsers = User::query()
            ->where('company_id', $document->company_id)
            ->pluck('name', 'id')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        $activityStatuses = Status::query()
            ->where('company_id', $document->company_id)
            ->get()
            ->mapWithKeys(function (Status $status): array {
                $label = method_exists($status, 'getTranslation')
                    ? (string) ($status->getTranslation('name', app()->getLocale(), false) ?: data_get($status, 'name'))
                    : (string) data_get($status, 'name', 'Estado');

                if (str_starts_with($label, '{')) {
                    $decoded = json_decode($label, true);
                    if (is_array($decoded)) {
                        $label = (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? 'Estado');
                    }
                }

                return [$status->id => $label];
            })
            ->all();

        $activityCategories = Category::query()
            ->where('company_id', $document->company_id)
            ->get()
            ->mapWithKeys(function (Category $category): array {
                $label = method_exists($category, 'getTranslation')
                    ? (string) ($category->getTranslation('name', app()->getLocale(), false) ?: data_get($category, 'name'))
                    : (string) data_get($category, 'name', 'Categoría');

                if (str_starts_with($label, '{')) {
                    $decoded = json_decode($label, true);
                    if (is_array($decoded)) {
                        $label = (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? 'Categoría');
                    }
                }

                return [$category->id => $label];
            })
            ->all();

        $priorityLabels = collect(Priority::cases())
            ->mapWithKeys(fn (Priority $priority): array => [$priority->value => (string) ($priority->getLabel() ?? $priority->value)])
            ->all();

        return view('documents.show', compact(
            'document',
            'latestAiRun',
            'latestAiOutput',
            'activityLog',
            'activityUsers',
            'activityStatuses',
            'activityCategories',
            'priorityLabels',
            'distributionTargets',
            'distributionDepartmentOptions',
            'distributedDepartmentIds',
            'distributionResponseDocumentOptions',
            'archiveLocationOptions',
            'documentLocationHistory',
        ));
    }

    public function updateArchiveLocation(Request $request, Document $document)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para gestionar archivo físico de este documento.');
        }

        if (! $user->hasRole(Role::ArchiveManager->value)) {
            abort(403, 'Solo el rol de archivo puede asignar ubicación física desde el portal.');
        }

        $validated = $request->validate([
            'physical_location_id' => 'required|integer|exists:physical_locations,id',
            'archive_note' => 'nullable|string|max:1000',
        ]);

        $location = PhysicalLocation::query()
            ->where('company_id', $document->company_id)
            ->where('is_active', true)
            ->find($validated['physical_location_id']);

        if (! $location) {
            return back()->withErrors([
                'physical_location_id' => 'La ubicación seleccionada no es válida para la empresa del documento.',
            ])->withInput();
        }

        $movementType = $document->physical_location_id ? 'moved' : 'stored';
        $saved = $document->moveToLocation(
            newLocation: $location,
            notes: $validated['archive_note'] ?? null,
            movedBy: $user,
            movementType: $movementType
        );

        if (! $saved) {
            return back()->withErrors([
                'physical_location_id' => 'No se pudo asignar la ubicación física.',
            ])->withInput();
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Ubicación física del documento actualizada.');
    }

    public function sendToDepartments(Request $request, Document $document)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para distribuir este documento.');
        }

        if (! $user->hasRole(Role::Receptionist->value) && ! $user->hasAnyRole(['admin', 'super_admin', 'branch_admin'])) {
            abort(403, 'Solo recepción o administración puede distribuir documentos a oficinas.');
        }

        $validated = $request->validate([
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer|exists:departments,id',
            'routing_note' => 'nullable|string|max:1000',
        ]);

        $departments = Department::query()
            ->where('company_id', $document->company_id)
            ->whereIn('id', $validated['department_ids'])
            ->get()
            ->keyBy('id');

        if ($departments->count() !== count(array_unique($validated['department_ids']))) {
            return back()->withErrors(['department_ids' => 'Hay oficinas inválidas para la empresa del documento.']);
        }

        $existingDepartmentIds = $document->distributions()
            ->whereHas('targets', fn ($query) => $query->whereIn('department_id', $validated['department_ids']))
            ->with('targets:id,document_distribution_id,department_id')
            ->get()
            ->flatMap(fn (DocumentDistribution $distribution) => $distribution->targets->pluck('department_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($existingDepartmentIds->isNotEmpty()) {
            return back()->withErrors([
                'department_ids' => 'Una o más oficinas ya fueron distribuidas para este documento. No pueden enviarse nuevamente.',
            ])->withInput();
        }

        DB::transaction(function () use ($document, $user, $validated): void {
            $distribution = DocumentDistribution::create([
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'created_by' => $user->id,
                'status' => 'open',
                'notes' => $validated['routing_note'] ?? null,
                'sent_at' => now(),
            ]);

            foreach (array_values(array_unique($validated['department_ids'])) as $index => $departmentId) {
                $target = $distribution->targets()->create([
                    'department_id' => (int) $departmentId,
                    'status' => 'sent',
                    'response_type' => 'none',
                    'routing_note' => $validated['routing_note'] ?? null,
                    'sent_at' => now(),
                    'last_activity_at' => now(),
                    'last_updated_by' => $user->id,
                ]);

                $officeUsers = User::query()
                    ->where('company_id', $document->company_id)
                    ->where('department_id', (int) $departmentId)
                    ->where('is_active', true)
                    ->get();

                foreach ($officeUsers as $officeUser) {
                    $officeUser->notify(new DocumentDistributedToOfficeNotification(
                        $document,
                        $target->fresh(['department', 'distribution']),
                        $user->name
                    ));
                }
            }
        });

        return redirect()->route('documents.show', $document)
            ->with('success', 'Documento distribuido a las oficinas seleccionadas.');
    }

    public function updateDistributionTarget(Request $request, Document $document, DocumentDistributionTarget $target)
    {
        $user = Auth::user();

        if (! $this->canAccessDocument($user, $document)) {
            abort(403, 'No tienes permiso para actualizar seguimiento.');
        }

        if ((int) $target->distribution->document_id !== (int) $document->id) {
            abort(404);
        }

        $validated = $request->validate([
            'action' => 'required|in:received,in_review,close,reject,respond_comment,respond_document',
            'note' => 'nullable|string|max:15000',
            'response_document_id' => 'nullable|integer|exists:documents,id',
            'response_file' => 'nullable|file',
            'response_document_title' => 'nullable|string|max:255',
        ]);

        $isDepartmentManager = $user->hasRole(Role::OfficeManager->value) && (int) $user->department_id === (int) $target->department_id;
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin', 'branch_admin']);

        if (! $isDepartmentManager && ! $isAdmin) {
            abort(403, 'No puedes actualizar este destinatario.');
        }

        $action = (string) $validated['action'];
        $note = isset($validated['note']) ? trim((string) $validated['note']) : null;
        $note = $note !== null && $note !== '' ? $this->sanitizeDistributionNote($note) : null;

        $target->last_updated_by = $user->id;
        $target->last_activity_at = now();

        if ($action === 'received') {
            $target->status = 'received';
            $target->received_at ??= now();
            $target->follow_up_note = $note ?: $target->follow_up_note;
        }

        if ($action === 'in_review') {
            $target->status = 'in_review';
            $target->reviewed_at ??= now();
            $target->follow_up_note = $note ?: $target->follow_up_note;
        }

        if ($action === 'close') {
            $target->status = 'closed';
            $target->closed_at = now();
            $target->follow_up_note = $note ?: $target->follow_up_note;
        }

        if ($action === 'reject') {
            if ($note === null || $note === '') {
                return back()->withErrors(['note' => 'Debes indicar el motivo del rechazo.'])->withInput();
            }

            $target->status = 'rejected';
            $target->response_type = 'comment';
            $target->rejected_reason = $note;
            $target->response_note = $note;
            $target->responded_at = now();
            $target->responded_by = $user->id;
        }

        if ($action === 'respond_comment') {
            if ($note === null || $note === '') {
                return back()->withErrors(['note' => 'Debes escribir un comentario de respuesta.'])->withInput();
            }

            $target->status = 'responded';
            $target->response_type = 'comment';
            $target->response_note = $note;
            $target->responded_at = now();
            $target->responded_by = $user->id;
        }

        if ($action === 'respond_document') {
            $responseDocumentId = (int) ($validated['response_document_id'] ?? 0);
            $responseDocument = null;

            if ($request->hasFile('response_file')) {
                $responseTitle = trim((string) ($validated['response_document_title'] ?? ''));
                if ($responseTitle === '') {
                    $responseTitle = 'Respuesta a '.$document->title;
                }

                $responseDocument = Document::create([
                    'company_id' => $document->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $user->department_id,
                    'category_id' => $document->category_id,
                    'status_id' => $document->status_id,
                    'created_by' => $user->id,
                    'assigned_to' => $user->id,
                    'title' => $responseTitle,
                    'description' => $note ? trim(strip_tags($note)) : null,
                    'content' => $note,
                    'file_path' => $this->documentFileService->storeUploadedFile($request->file('response_file')),
                    'priority' => $document->priority?->value ?? (string) ($document->priority ?? 'medium'),
                    'is_confidential' => (bool) $document->is_confidential,
                    'metadata' => array_filter([
                        'response_to_document_id' => $document->id,
                        'response_to_document_number' => $document->document_number,
                        'response_to_distribution_target_id' => $target->id,
                        'generated_as_office_response' => true,
                    ]),
                ]);
            } else {
                if ($responseDocumentId <= 0) {
                    return back()->withErrors(['response_document_id' => 'Selecciona un documento de respuesta o carga un archivo de respuesta.'])->withInput();
                }

                $responseDocument = Document::query()
                    ->where('company_id', $document->company_id)
                    ->where('id', $responseDocumentId)
                    ->first();

                if (! $responseDocument) {
                    return back()->withErrors(['response_document_id' => 'El documento de respuesta no pertenece a la empresa.'])->withInput();
                }

                if ($isDepartmentManager && (int) $responseDocument->department_id !== (int) $user->department_id) {
                    return back()->withErrors(['response_document_id' => 'Solo puedes responder con documentos de tu oficina.'])->withInput();
                }
            }

            $target->status = 'responded';
            $target->response_type = 'outgoing_document';
            $target->response_document_id = $responseDocument->id;
            $target->response_note = $note ?: $target->response_note;
            $target->responded_at = now();
            $target->responded_by = $user->id;
        }

        $target->save();
        $target->load(['department', 'distribution', 'responseDocument', 'respondedBy']);

        $creator = $document->creator;
        if ($creator && (int) $creator->id !== (int) $user->id) {
            $creator->notify(new DocumentDistributionTargetUpdatedNotification(
                $document->loadMissing('company', 'creator'),
                $target,
                $user->name
            ));
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Seguimiento de oficina actualizado.');
    }

    private function sanitizeDistributionNote(string $note): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h3><h4><a>';
        $sanitized = strip_tags($note, $allowedTags);

        return trim($sanitized);
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
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
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
            || ($user->hasRole(Role::OfficeManager->value) && $user->department_id && $document->distributions()
                ->whereHas('targets', fn ($q) => $q->where('department_id', $user->department_id))
                ->exists())
            || $user->hasRole(Role::ArchiveManager->value)
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
            || $document->assigned_to === $user->id
            || ($user->hasRole(Role::OfficeManager->value) && $user->department_id && $document->distributions()
                ->whereHas('targets', fn ($q) => $q->where('department_id', $user->department_id))
                ->exists());
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
        $canCreateRecipientUser = true;

        if ($recipientUser && (int) $recipientUser->company_id !== (int) $issuer->company_id) {
            $recipientUser = null;
            $canCreateRecipientUser = false;
        }

        if (! $recipientUser && $canCreateRecipientUser) {
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

        if ($recipientUser && ! $recipientUser->hasRole(Role::RegularUser->value)) {
            $recipientUser->assignRole(Role::RegularUser->value);
        }

        return Receipt::create([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'issued_by' => $issuer->id,
            'recipient_user_id' => $recipientUser?->id,
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

    private function makeTitleFromFilename(UploadedFile $file): string
    {
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $normalized = preg_replace('/[_\-]+/', ' ', $filename) ?? $filename;
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized) ?? (string) $normalized;

        return trim($normalized) !== '' ? Str::title(trim($normalized)) : 'Documento';
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
