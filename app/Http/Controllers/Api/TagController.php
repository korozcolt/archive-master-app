<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Http\Requests\Api\StoreTagRequest;
use App\Http\Requests\Api\UpdateTagRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Tags",
 *     description="API Endpoints for tag management"
 * )
 */
class TagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tags",
     *     summary="Get list of tags",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Tag::with(['company'])
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

        $query->orderBy('name');
        
        $perPage = min($request->get('per_page', 15), 100);
        $tags = $query->paginate($perPage);

        return TagResource::collection($tags);
    }

    /**
     * @OA\Post(
     *     path="/api/tags",
     *     summary="Create a new tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Important"),
     *             @OA\Property(property="description", type="string", example="Important documents"),
     *             @OA\Property(property="color", type="string", example="#FF0000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tag created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     )
     * )
     */
    public function store(StoreTagRequest $request)
    {
        $data = $request->validated();

        $tag = Tag::create($data);
        $tag->load(['company']);

        return new TagResource($tag);
    }

    /**
     * @OA\Get(
     *     path="/api/tags/{id}",
     *     summary="Get tag by ID",
     *     tags={"Tags"},
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
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     )
     * )
     */
    public function show(Tag $tag)
    {
        $this->authorize('view', $tag);
        
        $tag->load(['company', 'documents']);

        return new TagResource($tag);
    }

    /**
     * @OA\Put(
     *     path="/api/tags/{id}",
     *     summary="Update tag",
     *     tags={"Tags"},
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
     *             @OA\Property(property="name", type="string", example="Updated Tag Name"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     )
     * )
     */
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $data = $request->validated();

        $tag->update($data);
        $tag->load(['company']);

        return new TagResource($tag);
    }

    /**
     * @OA\Delete(
     *     path="/api/tags/{id}",
     *     summary="Delete tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Tag deleted successfully"
     *     )
     * )
     */
    public function destroy(Tag $tag)
    {
        $this->authorize('delete', $tag);
        
        // Detach from all documents before deleting
        $tag->documents()->detach();
        
        $tag->delete();
        
        return response()->noContent();
    }
}