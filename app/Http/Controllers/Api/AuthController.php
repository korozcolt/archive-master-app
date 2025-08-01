<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for authentication"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", example="password"),
     *             @OA\Property(property="device_name", type="string", example="Mobile App")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="1|abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Las credenciales proporcionadas son incorrectas.")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'device_name.required' => 'El nombre del dispositivo es obligatorio.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está desactivada. Contacta al administrador.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token
        $token = $user->createToken($request->device_name)->plainTextToken;

        // Load relationships
        $user->load(['company', 'branch', 'department', 'roles', 'permissions']);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada exitosamente.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente.'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout-all",
     *     summary="Logout from all devices",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout from all devices successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada en todos los dispositivos.")
     *         )
     *     )
     * )
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens for the user
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Sesión cerrada en todos los dispositivos.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get current user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user information",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['company', 'branch', 'department', 'roles', 'permissions']);
        
        return new UserResource($user);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     summary="Refresh token",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="device_name", type="string", example="Mobile App")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="2|def456...")
     *         )
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
        ], [
            'device_name.required' => 'El nombre del dispositivo es obligatorio.',
        ]);

        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken($request->device_name)->plainTextToken;
        
        // Load relationships
        $user->load(['company', 'branch', 'department', 'roles', 'permissions']);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/tokens",
     *     summary="Get user's active tokens",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of active tokens",
     *         @OA\JsonContent(
     *             @OA\Property(property="tokens", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="last_used_at", type="string"),
     *                 @OA\Property(property="created_at", type="string")
     *             ))
     *         )
     *     )
     * )
     */
    public function tokens(Request $request)
    {
        $tokens = $request->user()->tokens()->select('id', 'name', 'last_used_at', 'created_at')->get();

        return response()->json([
            'tokens' => $tokens
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/tokens/{tokenId}",
     *     summary="Revoke specific token",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tokenId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token revoked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Token revocado exitosamente.")
     *         )
     *     )
     * )
     */
    public function revokeToken(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();
        
        if (!$token) {
            return response()->json([
                'message' => 'Token no encontrado.'
            ], 404);
        }
        
        $token->delete();
        
        return response()->json([
            'message' => 'Token revocado exitosamente.'
        ]);
    }
}