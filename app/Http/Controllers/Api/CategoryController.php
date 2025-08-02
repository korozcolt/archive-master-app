<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CategoryController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/categories",
     *     tags={"Categories"},
     *     summary="Listar categorías",
     *     description="Obtener lista jerárquica de categorías disponibles para la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Contratos"),
     *                     @OA\Property(property="slug", type="string", example="contratos"),
     *                     @OA\Property(property="description", type="string", example="Documentos contractuales"),
     *                     @OA\Property(property="parent_id", type="integer", example=null),
     *                     @OA\Property(property="color", type="string", example="blue"),
     *                     @OA\Property(property="icon", type="string", example="heroicon-o-document-text"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="children",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        // Usar cache para categorías (30 minutos)
        $categories = CacheService::getActiveCategories();

        return $this->successResponse($categories);
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{id}",
     *     tags={"Categories"},
     *     summary="Obtener categoría",
     *     description="Obtener detalles de una categoría específica",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la categoría",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la categoría",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Contratos"),
     *                 @OA\Property(property="slug", type="string", example="contratos"),
     *                 @OA\Property(property="description", type="string", example="Documentos contractuales"),
     *                 @OA\Property(property="parent_id", type="integer", example=null),
     *                 @OA\Property(property="color", type="string", example="blue"),
     *                 @OA\Property(property="icon", type="string", example="heroicon-o-document-text"),
     *                 @OA\Property(property="active", type="boolean", example=true),
     *                 @OA\Property(property="parent", type="object"),
     *                 @OA\Property(property="children", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="documents_count", type="integer", example=25),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::where('company_id', Auth::user()->company_id)
            ->with(['parent', 'children', 'documents'])
            ->withCount('documents')
            ->find($id);

        if (!$category) {
            return $this->errorResponse('Categoría no encontrada', 404);
        }

        return $this->successResponse($category);
    }
}
