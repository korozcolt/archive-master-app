<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="ArchiveMaster API",
 *     version="1.0.0",
 *     description="Sistema de gestión documental empresarial completo",
 *     @OA\Contact(
 *         email="admin@archivemaster.com",
 *         name="ArchiveMaster Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="ArchiveMaster API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Usar Bearer Token para autenticación"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints de autenticación y autorización"
 * )
 *
 * @OA\Tag(
 *     name="Documents",
 *     description="Gestión de documentos"
 * )
 *
 * @OA\Tag(
 *     name="Users",
 *     description="Gestión de usuarios"
 * )
 *
 * @OA\Tag(
 *     name="Companies",
 *     description="Gestión de empresas"
 * )
 *
 * @OA\Tag(
 *     name="Search",
 *     description="Búsqueda avanzada de documentos"
 * )
 *
 * @OA\Tag(
 *     name="Reports",
 *     description="Generación de reportes"
 * )
 *
 * @OA\Tag(
 *     name="Workflows",
 *     description="Gestión de flujos de trabajo"
 * )
 *
 * @OA\Tag(
 *     name="Hardware",
 *     description="Integración con hardware (escáneres, lectores)"
 * )
 *
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Sistema de webhooks para integraciones"
 * )
 */
class BaseApiController extends Controller
{
    /**
     * Respuesta exitosa estándar
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    /**
     * Respuesta de error estándar
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    /**
     * Respuesta paginada estándar
     */
    protected function paginatedResponse($data, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
