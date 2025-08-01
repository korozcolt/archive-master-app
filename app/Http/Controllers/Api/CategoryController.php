<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Api\StoreCategoryRequest;
use App\Http\Requests\Api\UpdateCategoryRequest;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="API Endpoints for category management"
 * )
 */
class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/categories",
     *     summary="Get list of categories",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Category"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Category::with(['company', 'parent', 'children'])
            ->where('company_id', Auth::user()->company_id);

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

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->get('parent_id'));
        }

        $query->orderBy('sort_order')->orderBy('name');
        
        $perPage = min($request->get('per_page', 15), 100);
        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    /**
     * @OA\Post(
     *     path="/api/categories",
     *     summary="Create a new category",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Category Name"),
     *             @OA\Property(property="description", type="string", example="Category description"),
     *             @OA\Property(property="color", type="string", example="#FF5733")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     )
     * )
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        $category = Category::create($data);
        $category->load(['company', 'parent', 'children']);

        return new CategoryResource($category);
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{id}",
     *     summary="Get category by ID",
     *     tags={"Categories"},
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
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     )
     * )
     */
    public function show(Category $category)
    {
        $this->authorize('view', $category);
        
        $category->load(['company', 'parent', 'children', 'documents']);

        return new CategoryResource($category);
    }

    /**
     * @OA\Put(
     *     path="/api/categories/{id}",
     *     summary="Update category",
     *     tags={"Categories"},
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
     *             @OA\Property(property="name", type="string", example="Updated Category Name"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     )
     * )
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        $category->update($data);
        $category->load(['company', 'parent', 'children']);

        return new CategoryResource($category);
    }

    /**
     * @OA\Delete(
     *     path="/api/categories/{id}",
     *     summary="Delete category",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Category deleted successfully"
     *     )
     * )
     */
    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);
        
        // Check if category has documents
        if ($category->documents()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene documentos asociados.'
            ], 422);
        }
        
        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene subcategorías.'
            ], 422);
        }
        
        $category->delete();
        
        return response()->noContent();
    }
}