<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Listar usuarios",
     *     description="Obtener lista de usuarios de la empresa",
     *     security={{"bearerAuth":{}}},
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
     *         name="active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios",
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
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="department", type="object"),
     *                     @OA\Property(property="branch", type="object"),
     *                     @OA\Property(property="roles", type="array", @OA\Items(type="string"))
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('company_id', Auth::user()->company_id)
            ->with(['department', 'branch', 'roles']);

        // Aplicar filtros
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $users = $query->orderBy('name')->get();

        return $this->successResponse($users);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Obtener usuario",
     *     description="Obtener detalles de un usuario específico",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Juan Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan.perez@empresa.com"),
     *                 @OA\Property(property="position", type="string", example="Analista"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time"),
     *                 @OA\Property(property="company", type="object"),
     *                 @OA\Property(property="department", type="object"),
     *                 @OA\Property(property="branch", type="object"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="created_documents_count", type="integer", example=15),
     *                 @OA\Property(property="assigned_documents_count", type="integer", example=8),
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
        $user = User::where('company_id', Auth::user()->company_id)
            ->with(['company', 'department', 'branch', 'roles', 'permissions'])
            ->withCount(['createdDocuments', 'assignedDocuments'])
            ->find($id);

        if (!$user) {
            return $this->errorResponse('Usuario no encontrado', 404);
        }

        return $this->successResponse($user);
    }
}
