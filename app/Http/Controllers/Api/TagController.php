<?php

namespace App\Http\Controllers\Api;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TagController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/tags",
     *     tags={"Tags"},
     *     summary="Listar etiquetas",
     *     description="Obtener lista de etiquetas disponibles para la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar etiquetas por nombre",
     *         required=false,
     *         @OA\Schema(type="string", example="importante")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de etiquetas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Importante"),
     *                     @OA\Property(property="slug", type="string", example="importante"),
     *                     @OA\Property(property="color", type="string", example="red"),
     *                     @OA\Property(property="description", type="string", example="Documentos de alta prioridad"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="documents_count", type="integer", example=25),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::where('company_id', Auth::user()->company_id)
            ->where('active', true)
            ->withCount('documents');

        // Aplicar búsqueda si se proporciona
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $tags = $query->orderBy('name')->get();

        return $this->successResponse($tags);
    }

    /**
     * @OA\Get(
     *     path="/api/tags/{id}",
     *     tags={"Tags"},
     *     summary="Obtener etiqueta",
     *     description="Obtener detalles de una etiqueta específica",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la etiqueta",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la etiqueta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Importante"),
     *                 @OA\Property(property="slug", type="string", example="importante"),
     *                 @OA\Property(property="color", type="string", example="red"),
     *                 @OA\Property(property="description", type="string", example="Documentos de alta prioridad"),
     *                 @OA\Property(property="active", type="boolean", example=true),
     *                 @OA\Property(property="documents_count", type="integer", example=25),
     *                 @OA\Property(
     *                     property="recent_documents",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Contrato Importante"),
     *                         @OA\Property(property="document_number", type="string", example="DOC-001"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Etiqueta no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Etiqueta no encontrada"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $tag = Tag::where('company_id', Auth::user()->company_id)
            ->withCount('documents')
            ->find($id);

        if (!$tag) {
            return $this->errorResponse('Etiqueta no encontrada', 404);
        }

        // Obtener documentos recientes con esta etiqueta
        $recentDocuments = $tag->documents()
            ->select(['id', 'title', 'document_number', 'created_at'])
            ->latest()
            ->limit(5)
            ->get();

        $tagData = $tag->toArray();
        $tagData['recent_documents'] = $recentDocuments;

        return $this->successResponse($tagData);
    }

    /**
     * @OA\Get(
     *     path="/api/tags/popular",
     *     tags={"Tags"},
     *     summary="Etiquetas populares",
     *     description="Obtener las etiquetas más utilizadas en la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Límite de etiquetas a retornar (máximo 50)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Etiquetas más populares",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Importante"),
     *                     @OA\Property(property="slug", type="string", example="importante"),
     *                     @OA\Property(property="color", type="string", example="red"),
     *                     @OA\Property(property="documents_count", type="integer", example=25),
     *                     @OA\Property(property="usage_percentage", type="number", format="float", example=15.5)
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $companyId = Auth::user()->company_id;

        $totalDocuments = \App\Models\Document::where('company_id', $companyId)->count();

        $popularTags = Tag::where('company_id', $companyId)
            ->where('active', true)
            ->withCount('documents')
            ->having('documents_count', '>', 0)
            ->orderByDesc('documents_count')
            ->limit($limit)
            ->get()
            ->map(function ($tag) use ($totalDocuments) {
                $tagData = $tag->toArray();
                $tagData['usage_percentage'] = $totalDocuments > 0
                    ? round(($tag->documents_count / $totalDocuments) * 100, 1)
                    : 0;
                return $tagData;
            });

        return $this->successResponse($popularTags);
    }
}
