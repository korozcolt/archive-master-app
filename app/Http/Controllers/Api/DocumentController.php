<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Models\Status;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DocumentController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/documents",
     *     tags={"Documents"},
     *     summary="Listar documentos",
     *     description="Obtener lista paginada de documentos con filtros opcionales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Elementos por página (máximo 100)",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Búsqueda en título, descripción y número de documento",
     *         required=false,
     *         @OA\Schema(type="string", example="contrato")
     *     ),
     *     @OA\Parameter(
     *         name="status_id",
     *         in="query",
     *         description="Filtrar por estado",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrar por categoría",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="assigned_to",
     *         in="query",
     *         description="Filtrar por usuario asignado",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de documentos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                     @OA\Property(property="title", type="string", example="Contrato de Servicios"),
     *                     @OA\Property(property="description", type="string", example="Contrato de servicios profesionales"),
     *                     @OA\Property(property="status", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="En Proceso")
     *                     ),
     *                     @OA\Property(property="category", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Contratos")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75)
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        // Generar clave de cache basada en filtros
        $cacheKey = 'documents_list_' . md5(serialize([
            'company_id' => Auth::user()->company_id,
            'search' => $request->get('search'),
            'status_id' => $request->get('status_id'),
            'category_id' => $request->get('category_id'),
            'assigned_to' => $request->get('assigned_to'),
            'page' => $request->get('page', 1),
            'per_page' => $perPage,
        ]));

        // Intentar obtener del cache (5 minutos)
        $documents = CacheService::remember('documents', $cacheKey, function () use ($request, $perPage) {
            $query = Document::with(['status', 'category', 'creator', 'assignee', 'company'])
                ->where('company_id', Auth::user()->company_id);

            // Aplicar filtros
            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('status_id')) {
                $query->where('status_id', $request->status_id);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            return $query->latest()->paginate($perPage);
        }, 5); // Cache por 5 minutos

        return $this->paginatedResponse($documents);
    }

    /**
     * @OA\Post(
     *     path="/api/documents",
     *     tags={"Documents"},
     *     summary="Crear documento",
     *     description="Crear un nuevo documento",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","category_id","status_id"},
     *             @OA\Property(property="title", type="string", example="Nuevo Contrato"),
     *             @OA\Property(property="description", type="string", example="Descripción del documento"),
     *             @OA\Property(property="content", type="string", example="Contenido del documento"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="status_id", type="integer", example=1),
     *             @OA\Property(property="assigned_to", type="integer", example=2),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="priority", type="string", enum={"low","medium","high"}, example="medium"),
     *             @OA\Property(property="is_confidential", type="boolean", example=false),
     *             @OA\Property(property="due_at", type="string", format="date-time", example="2025-02-01T10:00:00Z"),
     *             @OA\Property(property="physical_location", type="string", example="Archivo Central - Estante A1"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Documento creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento creado exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                 @OA\Property(property="barcode", type="string", example="DOCABC20250101120000A1B2XYZ123"),
     *                 @OA\Property(property="qrcode", type="string", example="{""id"":1,""document_number"":""DOC-ABC-20250101120000-A1B2""}"),
     *                 @OA\Property(property="title", type="string", example="Nuevo Contrato"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Errores de validación"),
     *             @OA\Property(property="errors", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status_id' => 'required|exists:statuses,id',
            'assigned_to' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'priority' => 'nullable|in:low,medium,high',
            'is_confidential' => 'boolean',
            'due_at' => 'nullable|date',
            'physical_location' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $data = $validator->validated();
        $data['company_id'] = Auth::user()->company_id;
        $data['created_by'] = Auth::id();

        $document = Document::create($data);

        // Sincronizar tags si se proporcionaron
        if (isset($data['tags'])) {
            $document->syncTags($data['tags']);
        }

        $document->load(['status', 'category', 'creator', 'assignee', 'tags']);

        // Invalidar cache relacionado con documentos
        CacheService::invalidateDocumentCache();

        return $this->successResponse($document, 'Documento creado exitosamente', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/documents/{id}",
     *     tags={"Documents"},
     *     summary="Obtener documento",
     *     description="Obtener detalles de un documento específico",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del documento",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del documento",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                 @OA\Property(property="barcode", type="string", example="DOCABC20250101120000A1B2XYZ123"),
     *                 @OA\Property(property="qrcode", type="string", example="{""id"":1,""document_number"":""DOC-ABC-20250101120000-A1B2""}"),
     *                 @OA\Property(property="title", type="string", example="Contrato de Servicios"),
     *                 @OA\Property(property="description", type="string", example="Descripción detallada"),
     *                 @OA\Property(property="content", type="string", example="Contenido completo del documento"),
     *                 @OA\Property(property="status", type="object"),
     *                 @OA\Property(property="category", type="object"),
     *                 @OA\Property(property="creator", type="object"),
     *                 @OA\Property(property="assignee", type="object"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="versions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="workflow_history", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Documento no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Documento no encontrado"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $document = Document::with([
            'status', 'category', 'creator', 'assignee', 'company', 'branch', 'department',
            'tags', 'versions', 'workflowHistory.performedBy', 'workflowHistory.fromStatus', 'workflowHistory.toStatus'
        ])
        ->where('company_id', Auth::user()->company_id)
        ->find($id);

        if (!$document) {
            return $this->errorResponse('Documento no encontrado', 404);
        }

        return $this->successResponse($document);
    }

    /**
     * @OA\Put(
     *     path="/api/documents/{id}",
     *     tags={"Documents"},
     *     summary="Actualizar documento",
     *     description="Actualizar un documento existente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del documento",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Título actualizado"),
     *             @OA\Property(property="description", type="string", example="Nueva descripción"),
     *             @OA\Property(property="content", type="string", example="Contenido actualizado"),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="assigned_to", type="integer", example=3),
     *             @OA\Property(property="priority", type="string", enum={"low","medium","high"}, example="high"),
     *             @OA\Property(property="is_confidential", type="boolean", example=true),
     *             @OA\Property(property="due_at", type="string", format="date-time", example="2025-03-01T10:00:00Z"),
     *             @OA\Property(property="physical_location", type="string", example="Archivo Central - Estante B2"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer"), example={2,3,4})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento actualizado exitosamente"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::where('company_id', Auth::user()->company_id)->find($id);

        if (!$document) {
            return $this->errorResponse('Documento no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'priority' => 'nullable|in:low,medium,high',
            'is_confidential' => 'boolean',
            'due_at' => 'nullable|date',
            'physical_location' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $data = $validator->validated();
        $document->update($data);

        // Sincronizar tags si se proporcionaron
        if (isset($data['tags'])) {
            $document->syncTags($data['tags']);
        }

        $document->load(['status', 'category', 'creator', 'assignee', 'tags']);

        // Invalidar cache relacionado con documentos
        CacheService::invalidateDocumentCache();

        return $this->successResponse($document, 'Documento actualizado exitosamente');
    }

    /**
     * @OA\Delete(
     *     path="/api/documents/{id}",
     *     tags={"Documents"},
     *     summary="Eliminar documento",
     *     description="Eliminar un documento (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del documento",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento eliminado exitosamente"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $document = Document::where('company_id', Auth::user()->company_id)->find($id);

        if (!$document) {
            return $this->errorResponse('Documento no encontrado', 404);
        }

        $document->delete();

        return $this->successResponse(null, 'Documento eliminado exitosamente');
    }

    /**
     * @OA\Post(
     *     path="/api/documents/{id}/transition",
     *     tags={"Documents"},
     *     summary="Transicionar documento",
     *     description="Cambiar el estado de un documento siguiendo el workflow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del documento",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status_id"},
     *             @OA\Property(property="status_id", type="integer", example=2, description="ID del nuevo estado"),
     *             @OA\Property(property="comment", type="string", example="Documento revisado y aprobado", description="Comentario sobre la transición")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transición exitosa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento transicionado exitosamente"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Transición no válida",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transición no válida"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function transition(Request $request, int $id): JsonResponse
    {
        $document = Document::where('company_id', Auth::user()->company_id)->find($id);

        if (!$document) {
            return $this->errorResponse('Documento no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:statuses,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $newStatus = Status::find($request->status_id);

        if (!$document->canTransitionTo($newStatus, Auth::user())) {
            return $this->errorResponse('Transición no válida o sin permisos', 400);
        }

        try {
            $document->transitionTo($newStatus, $request->comment, Auth::user());
            $document->load(['status', 'workflowHistory.performedBy']);

            return $this->successResponse($document, 'Documento transicionado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al procesar la transición: ' . $e->getMessage(), 500);
        }
    }
}
