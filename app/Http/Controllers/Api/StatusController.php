<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StatusResource;
use App\Models\Status;
use Illuminate\Http\Request;
use App\Http\Requests\Api\StoreStatusRequest;
use App\Http\Requests\Api\UpdateStatusRequest;

/**
 * @OA\Tag(
 *     name="Statuses",
 *     description="API Endpoints for status management"
 * )
 */
class StatusController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/statuses",
     *     summary="Get list of statuses",
     *     tags={"Statuses"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Status"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Status::with(['company'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_final')) {
            $query->where('is_final', $request->boolean('is_final'));
        }

        $query->orderBy('sort_order')->orderBy('name');
        
        $perPage = min($request->get('per_page', 15), 100);
        $statuses = $query->paginate($perPage);

        return StatusResource::collection($statuses);
    }

    /**
     * @OA\Post(
     *     path="/api/statuses",
     *     summary="Create a new status",
     *     tags={"Statuses"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="In Progress"),
     *             @OA\Property(property="description", type="string", example="Document is being processed"),
     *             @OA\Property(property="color", type="string", example="#FFA500")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Status created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Status")
     *     )
     * )
     */
    public function store(StoreStatusRequest $request)
    {
        $data = $request->validated();

        $status = Status::create($data);
        $status->load(['company']);

        return new StatusResource($status);
    }

    /**
     * @OA\Get(
     *     path="/api/statuses/{id}",
     *     summary="Get status by ID",
     *     tags={"Statuses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Status")
     *     )
     * )
     */
    public function show(Status $status)
    {
        $this->authorize('view', $status);
        
        $status->load(['company', 'documents']);

        return new StatusResource($status);
    }

    /**
     * @OA\Put(
     *     path="/api/statuses/{id}",
     *     summary="Update status",
     *     tags={"Statuses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Status Name"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Status")
     *     )
     * )
     */
    public function update(UpdateStatusRequest $request, Status $status)
    {
        $data = $request->validated();

        $status->update($data);
        $status->load(['company']);

        return new StatusResource($status);
    }

    /**
     * @OA\Delete(
     *     path="/api/statuses/{id}",
     *     summary="Delete status",
     *     tags={"Statuses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Status deleted successfully"
     *     )
     * )
     */
    public function destroy(Status $status)
    {
        $this->authorize('delete', $status);
        
        // Check if status has documents
        if ($status->documents()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un estado que tiene documentos asociados.'
            ], 422);
        }
        
        $status->delete();
        
        return response()->noContent();
    }
}