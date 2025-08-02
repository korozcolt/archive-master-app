<?php

namespace App\Http\Controllers\Api;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StatusController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/statuses",
     *     tags={"Statuses"},
     *     summary="Listar estados",
     *     description="Obtener lista de estados disponibles para la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de estados",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="En Proceso"),
     *                     @OA\Property(property="slug", type="string", example="in_process"),
     *                     @OA\Property(property="color", type="string", example="blue"),
     *                     @OA\Property(property="icon", type="string", example="heroicon-o-clock"),
     *                     @OA\Property(property="is_initial", type="boolean", example=false),
     *                     @OA\Property(property="is_final", type="boolean", example=false),
     *                     @OA\Property(property="active", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $statuses = Status::where('company_id', Auth::user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse($statuses);
    }

    /**
     * @OA\Get(
     *     path="/api/statuses/{id}",
     *     tags={"Statuses"},
     *     summary="Obtener estado",
     *     description="Obtener detalles de un estado específico",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del estado",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del estado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="En Proceso"),
     *                 @OA\Property(property="slug", type="string", example="in_process"),
     *                 @OA\Property(property="color", type="string", example="blue"),
     *                 @OA\Property(property="icon", type="string", example="heroicon-o-clock"),
     *                 @OA\Property(property="is_initial", type="boolean", example=false),
     *                 @OA\Property(property="is_final", type="boolean", example=false),
     *                 @OA\Property(property="active", type="boolean", example=true),
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
        $status = Status::where('company_id', Auth::user()->company_id)
            ->find($id);

        if (!$status) {
            return $this->errorResponse('Estado no encontrado', 404);
        }

        return $this->successResponse($status);
    }
}
