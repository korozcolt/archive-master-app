<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\Builder;

class SearchController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/search/documents",
     *     tags={"Search"},
     *     summary="Búsqueda avanzada de documentos",
     *     description="Realizar búsqueda full-text y filtrada de documentos",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Término de búsqueda (full-text)",
     *         required=false,
     *         @OA\Schema(type="string", example="contrato servicios")
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
     *         name="created_by",
     *         in="query",
     *         description="Filtrar por creador",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="assigned_to",
     *         in="query",
     *         description="Filtrar por asignado",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Fecha desde (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha hasta (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="is_confidential",
     *         in="query",
     *         description="Filtrar por confidencialidad",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filtrar por prioridad",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low","medium","high"}, example="high")
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Filtrar por tags (IDs separados por coma)",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
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
     *     @OA\Response(
     *         response=200,
     *         description="Resultados de búsqueda",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="documents",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                         @OA\Property(property="title", type="string", example="Contrato de Servicios"),
     *                         @OA\Property(property="description", type="string", example="Contrato de servicios profesionales"),
     *                         @OA\Property(property="status", type="object"),
     *                         @OA\Property(property="category", type="object"),
     *                         @OA\Property(property="relevance_score", type="number", format="float", example=0.95)
     *                     )
     *                 ),
     *                 @OA\Property(property="total_results", type="integer", example=25),
     *                 @OA\Property(property="search_time_ms", type="integer", example=45),
     *                 @OA\Property(property="filters_applied", type="object")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function searchDocuments(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $perPage = min($request->get('per_page', 15), 100);
        $companyId = Auth::user()->company_id;

        // Si hay término de búsqueda, usar Scout
        if ($request->has('q') && !empty($request->q)) {
            $query = Document::search($request->q)
                ->where('company_id', $companyId);

            // Aplicar filtros adicionales
            $this->applySearchFilters($query, $request);

            $results = $query->paginate($perPage);
            $documents = $results->load(['status', 'category', 'creator', 'assignee']);
        } else {
            // Búsqueda por filtros sin término de búsqueda
            $query = Document::with(['status', 'category', 'creator', 'assignee'])
                ->where('company_id', $companyId);

            $this->applyDatabaseFilters($query, $request);

            $results = $query->latest()->paginate($perPage);
            $documents = $results;
        }

        $searchTime = round((microtime(true) - $startTime) * 1000);

        return $this->successResponse([
            'documents' => $documents->items(),
            'total_results' => $documents->total(),
            'search_time_ms' => $searchTime,
            'filters_applied' => $this->getAppliedFilters($request),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/search/users",
     *     tags={"Search"},
     *     summary="Búsqueda de usuarios",
     *     description="Buscar usuarios por nombre, email o posición",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Término de búsqueda",
     *         required=true,
     *         @OA\Schema(type="string", example="juan perez")
     *     ),
     *     @OA\Parameter(
     *         name="department_id",
     *         in="query",
     *         description="Filtrar por departamento",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="Filtrar por sucursal",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Límite de resultados (máximo 50)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultados de búsqueda de usuarios",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                     @OA\Property(property="email", type="string", example="juan.perez@empresa.com"),
     *                     @OA\Property(property="position", type="string", example="Analista"),
     *                     @OA\Property(property="department", type="object"),
     *                     @OA\Property(property="branch", type="object")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $limit = min($request->get('limit', 10), 50);
        $companyId = Auth::user()->company_id;

        if (empty($query)) {
            return $this->errorResponse('Parámetro de búsqueda requerido', 400);
        }

        $searchQuery = User::search($query)
            ->where('company_id', $companyId);

        if ($request->has('department_id')) {
            $searchQuery->where('department_id', $request->department_id);
        }

        if ($request->has('branch_id')) {
            $searchQuery->where('branch_id', $request->branch_id);
        }

        $users = $searchQuery->take($limit)->get()
            ->load(['department', 'branch', 'roles']);

        return $this->successResponse($users);
    }

    /**
     * @OA\Get(
     *     path="/api/search/suggestions",
     *     tags={"Search"},
     *     summary="Sugerencias de búsqueda",
     *     description="Obtener sugerencias de búsqueda basadas en términos populares",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Término parcial para sugerencias",
     *         required=false,
     *         @OA\Schema(type="string", example="cont")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Límite de sugerencias (máximo 20)",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sugerencias de búsqueda",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="suggestions",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="term", type="string", example="contrato"),
     *                         @OA\Property(property="count", type="integer", example=15),
     *                         @OA\Property(property="type", type="string", example="title")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="popular_searches",
     *                     type="array",
     *                     @OA\Items(type="string", example="contrato servicios")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 5), 20);
        $companyId = Auth::user()->company_id;

        $suggestions = [];
        $popularSearches = [];

        if (!empty($query)) {
            // Buscar en títulos de documentos
            $titleSuggestions = Document::where('company_id', $companyId)
                ->where('title', 'LIKE', "%{$query}%")
                ->select('title')
                ->distinct()
                ->limit($limit)
                ->pluck('title')
                ->map(function ($title) {
                    return [
                        'term' => $title,
                        'type' => 'title',
                        'count' => 1
                    ];
                });

            $suggestions = array_merge($suggestions, $titleSuggestions->toArray());
        }

        // Búsquedas populares (simuladas - en producción se guardarían en cache/DB)
        $popularSearches = [
            'contrato servicios',
            'factura',
            'reporte mensual',
            'acta reunion',
            'propuesta comercial'
        ];

        return $this->successResponse([
            'suggestions' => array_slice($suggestions, 0, $limit),
            'popular_searches' => array_slice($popularSearches, 0, $limit)
        ]);
    }

    /**
     * Aplicar filtros a la búsqueda Scout
     */
    private function applySearchFilters(Builder $query, Request $request): void
    {
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('is_confidential')) {
            $query->where('is_confidential', $request->boolean('is_confidential'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
    }

    /**
     * Aplicar filtros a la consulta de base de datos
     */
    private function applyDatabaseFilters($query, Request $request): void
    {
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('is_confidential')) {
            $query->where('is_confidential', $request->boolean('is_confidential'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('tags')) {
            $tagIds = explode(',', $request->tags);
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }
    }

    /**
     * Obtener filtros aplicados para la respuesta
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('q')) {
            $filters['search_term'] = $request->q;
        }

        if ($request->has('status_id')) {
            $filters['status_id'] = $request->status_id;
        }

        if ($request->has('category_id')) {
            $filters['category_id'] = $request->category_id;
        }

        if ($request->has('date_from')) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->has('date_to')) {
            $filters['date_to'] = $request->date_to;
        }

        return $filters;
    }
}
