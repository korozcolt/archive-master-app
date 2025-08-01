<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for user management"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get list of users",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        
        $query = User::with(['company', 'branch', 'department', 'roles'])
            ->where('company_id', Auth::user()->company_id);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->get('department_id'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create a new user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="position", type="string", example="Manager")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);
        
        $data = $request->validated();
        $data['company_id'] = Auth::user()->company_id;
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        
        // Assign roles if provided
        if ($request->has('roles')) {
            $user->assignRole($request->get('roles'));
        }
        
        $user->load(['company', 'branch', 'department', 'roles']);

        return new UserResource($user);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get user by ID",
     *     tags={"Users"},
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
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        
        $user->load(['company', 'branch', 'department', 'roles', 'permissions']);

        return new UserResource($user);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Update user",
     *     tags={"Users"},
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
     *             @OA\Property(property="name", type="string", example="John Doe Updated"),
     *             @OA\Property(property="position", type="string", example="Senior Manager")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);
        
        $data = $request->validated();
        
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        
        // Update roles if provided
        if ($request->has('roles')) {
            $user->syncRoles($request->get('roles'));
        }
        
        $user->load(['company', 'branch', 'department', 'roles']);

        return new UserResource($user);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="User deleted successfully"
     *     )
     * )
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        
        // Prevent deleting the last admin user
        if ($user->hasRole('admin')) {
            $adminCount = User::role('admin')
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->count();
            
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'No se puede eliminar el último usuario administrador.'
                ], 422);
            }
        }
        
        $user->delete();
        
        return response()->noContent();
    }

    /**
     * @OA\Get(
     *     path="/api/users/me",
     *     summary="Get current authenticated user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user information",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function me()
    {
        $user = Auth::user();
        $user->load(['company', 'branch', 'department', 'roles', 'permissions']);
        
        return new UserResource($user);
    }
}