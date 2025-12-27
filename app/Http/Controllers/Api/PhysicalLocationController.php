<?php

namespace App\Http\Controllers\Api;

use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PhysicalLocationController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/physical-locations",
     *     tags={"Physical Locations"},
     *     summary="Listar ubicaciones físicas",
     *     description="Obtener lista paginada de ubicaciones físicas de la empresa",
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
     *         description="Elementos por página",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="template_id",
     *         in="query",
     *         description="Filtrar por plantilla",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="available",
     *         in="query",
     *         description="Solo ubicaciones con capacidad disponible",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de ubicaciones físicas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PhysicalLocation::query()
            ->where('company_id', Auth::user()->company_id)
            ->with(['template', 'company', 'documents'])
            ->withCount('documents');

        // Filtrar por plantilla
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        // Filtrar por estado activo
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrar solo disponibles (con capacidad)
        if ($request->filled('available') && filter_var($request->available, FILTER_VALIDATE_BOOLEAN)) {
            $query->available();
        }

        // Ordenar
        $query->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_order', 'desc'));

        $perPage = min($request->input('per_page', 15), 100);
        $locations = $query->paginate($perPage);

        return $this->paginatedResponse($locations, 'Ubicaciones físicas obtenidas exitosamente');
    }

    /**
     * @OA\Get(
     *     path="/api/physical-locations/{id}",
     *     tags={"Physical Locations"},
     *     summary="Obtener ubicación física",
     *     description="Obtener detalles de una ubicación física específica",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la ubicación",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la ubicación física",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ubicación no encontrada"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $location = PhysicalLocation::where('company_id', Auth::user()->company_id)
            ->with(['template', 'company', 'documents', 'createdBy', 'locationHistory'])
            ->withCount('documents')
            ->find($id);

        if (!$location) {
            return $this->errorResponse('Ubicación física no encontrada', 404);
        }

        // Agregar información adicional
        $location->capacity_percentage = $location->getCapacityPercentage();
        $location->is_full = $location->isFull();
        $location->document_count = $location->getDocumentCount();

        return $this->successResponse($location, 'Ubicación física obtenida exitosamente');
    }

    /**
     * @OA\Post(
     *     path="/api/physical-locations",
     *     tags={"Physical Locations"},
     *     summary="Crear ubicación física",
     *     description="Crear una nueva ubicación física",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"template_id", "structured_data"},
     *             @OA\Property(property="template_id", type="integer", example=1),
     *             @OA\Property(property="structured_data", type="object", example={"edificio": "A", "piso": "3"}),
     *             @OA\Property(property="capacity_total", type="integer", example=100),
     *             @OA\Property(property="notes", type="string", example="Ubicación principal"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Ubicación creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ubicación física creada exitosamente"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:physical_location_templates,id',
            'structured_data' => 'required|array',
            'capacity_total' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:65535',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Error de validación', 422, $validator->errors());
        }

        // Verificar que la plantilla pertenezca a la empresa del usuario
        $template = PhysicalLocationTemplate::where('id', $request->template_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$template) {
            return $this->errorResponse('Plantilla no encontrada o no pertenece a su empresa', 404);
        }

        try {
            $location = PhysicalLocation::create([
                'company_id' => Auth::user()->company_id,
                'template_id' => $request->template_id,
                'structured_data' => $request->structured_data,
                'capacity_total' => $request->capacity_total,
                'notes' => $request->notes,
                'is_active' => $request->input('is_active', true),
            ]);

            $location->load(['template', 'company']);

            Log::info('Ubicación física creada vía API', [
                'location_id' => $location->id,
                'code' => $location->code,
                'user_id' => Auth::id(),
            ]);

            return $this->successResponse(
                $location,
                'Ubicación física creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            Log::error('Error al crear ubicación física', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse('Error al crear ubicación física', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/physical-locations/{id}",
     *     tags={"Physical Locations"},
     *     summary="Actualizar ubicación física",
     *     description="Actualizar una ubicación física existente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la ubicación",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="structured_data", type="object", example={"edificio": "B", "piso": "2"}),
     *             @OA\Property(property="capacity_total", type="integer", example=150),
     *             @OA\Property(property="notes", type="string", example="Ubicación actualizada"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ubicación actualizada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ubicación no encontrada"
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $location = PhysicalLocation::where('company_id', Auth::user()->company_id)->find($id);

        if (!$location) {
            return $this->errorResponse('Ubicación física no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'structured_data' => 'nullable|array',
            'capacity_total' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:65535',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Error de validación', 422, $validator->errors());
        }

        // No permitir cambiar capacity_total si es menor que capacity_used
        if ($request->filled('capacity_total') && $request->capacity_total < $location->capacity_used) {
            return $this->errorResponse(
                'La capacidad total no puede ser menor que la capacidad usada actual',
                422
            );
        }

        try {
            $location->update($request->only(['structured_data', 'capacity_total', 'notes', 'is_active']));
            $location->load(['template', 'company', 'documents']);

            Log::info('Ubicación física actualizada vía API', [
                'location_id' => $location->id,
                'code' => $location->code,
                'user_id' => Auth::id(),
            ]);

            return $this->successResponse($location, 'Ubicación física actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar ubicación física', [
                'error' => $e->getMessage(),
                'location_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse('Error al actualizar ubicación física', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/physical-locations/{id}",
     *     tags={"Physical Locations"},
     *     summary="Eliminar ubicación física",
     *     description="Eliminar una ubicación física (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la ubicación",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ubicación eliminada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ubicación no encontrada"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="No se puede eliminar ubicación con documentos"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $location = PhysicalLocation::where('company_id', Auth::user()->company_id)->find($id);

        if (!$location) {
            return $this->errorResponse('Ubicación física no encontrada', 404);
        }

        // Verificar si tiene documentos asociados
        if ($location->documents()->count() > 0) {
            return $this->errorResponse(
                'No se puede eliminar la ubicación porque tiene documentos asociados',
                422
            );
        }

        try {
            $location->delete();

            Log::info('Ubicación física eliminada vía API', [
                'location_id' => $id,
                'code' => $location->code,
                'user_id' => Auth::id(),
            ]);

            return $this->successResponse(null, 'Ubicación física eliminada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar ubicación física', [
                'error' => $e->getMessage(),
                'location_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse('Error al eliminar ubicación física', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/physical-locations/search",
     *     tags={"Physical Locations"},
     *     summary="Buscar ubicaciones físicas",
     *     description="Buscar ubicaciones por código, path o datos estructurados",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Término de búsqueda",
     *         required=true,
     *         @OA\Schema(type="string", example="edificio")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultados de búsqueda"
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Error de validación', 422, $validator->errors());
        }

        $query = $request->input('query');

        $locations = PhysicalLocation::where('company_id', Auth::user()->company_id)
            ->search($query)
            ->with(['template', 'documents'])
            ->withCount('documents')
            ->limit(20)
            ->get();

        return $this->successResponse($locations, 'Búsqueda completada');
    }

    /**
     * @OA\Get(
     *     path="/api/physical-locations/{id}/capacity",
     *     tags={"Physical Locations"},
     *     summary="Verificar capacidad de ubicación",
     *     description="Obtener información detallada de capacidad de una ubicación",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la ubicación",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Información de capacidad"
     *     )
     * )
     */
    public function checkCapacity(int $id): JsonResponse
    {
        $location = PhysicalLocation::where('company_id', Auth::user()->company_id)->find($id);

        if (!$location) {
            return $this->errorResponse('Ubicación física no encontrada', 404);
        }

        $capacityInfo = [
            'location_id' => $location->id,
            'code' => $location->code,
            'full_path' => $location->full_path,
            'capacity_total' => $location->capacity_total,
            'capacity_used' => $location->capacity_used,
            'capacity_available' => $location->capacity_total ? $location->capacity_total - $location->capacity_used : null,
            'capacity_percentage' => $location->getCapacityPercentage(),
            'is_full' => $location->isFull(),
            'has_capacity_limit' => $location->capacity_total !== null,
            'document_count' => $location->getDocumentCount(),
        ];

        return $this->successResponse($capacityInfo, 'Información de capacidad obtenida exitosamente');
    }

    /**
     * @OA\Get(
     *     path="/api/physical-locations/available",
     *     tags={"Physical Locations"},
     *     summary="Listar ubicaciones disponibles",
     *     description="Obtener solo ubicaciones con capacidad disponible",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de ubicaciones disponibles"
     *     )
     * )
     */
    public function available(): JsonResponse
    {
        $locations = PhysicalLocation::where('company_id', Auth::user()->company_id)
            ->active()
            ->available()
            ->with(['template'])
            ->withCount('documents')
            ->get()
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'code' => $location->code,
                    'full_path' => $location->full_path,
                    'capacity_available' => $location->capacity_total - $location->capacity_used,
                    'capacity_percentage' => $location->getCapacityPercentage(),
                    'template' => $location->template->name ?? null,
                ];
            });

        return $this->successResponse($locations, 'Ubicaciones disponibles obtenidas exitosamente');
    }

    /**
     * @OA\Get(
     *     path="/api/physical-locations/{code}/by-code",
     *     tags={"Physical Locations"},
     *     summary="Obtener ubicación por código",
     *     description="Buscar una ubicación específica por su código único",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Código de la ubicación",
     *         required=true,
     *         @OA\Schema(type="string", example="ED-A/P-3/SALA-ARCH")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ubicación encontrada"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ubicación no encontrada"
     *     )
     * )
     */
    public function findByCode(string $code): JsonResponse
    {
        $location = PhysicalLocation::where('company_id', Auth::user()->company_id)
            ->byCode($code)
            ->with(['template', 'documents'])
            ->withCount('documents')
            ->first();

        if (!$location) {
            return $this->errorResponse('Ubicación física no encontrada con el código especificado', 404);
        }

        $location->capacity_percentage = $location->getCapacityPercentage();
        $location->is_full = $location->isFull();

        return $this->successResponse($location, 'Ubicación física encontrada exitosamente');
    }
}
