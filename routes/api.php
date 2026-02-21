<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HardwareController;
use App\Http\Controllers\Api\PhysicalLocationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Rutas protegidas con autenticación
Route::middleware('auth:sanctum')->group(function () {

    // Autenticación
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Gestión de documentos
    Route::apiResource('documents', DocumentController::class);
    Route::post('documents/{id}/transition', [DocumentController::class, 'transition']);

    // Gestión de ubicaciones físicas
    Route::prefix('physical-locations')->group(function () {
        Route::get('/', [PhysicalLocationController::class, 'index']);
        Route::get('/search', [PhysicalLocationController::class, 'search']);
        Route::get('/available', [PhysicalLocationController::class, 'available']);
        Route::get('/{code}/by-code', [PhysicalLocationController::class, 'findByCode'])
            ->where('code', '.*');
        Route::get('/{id}', [PhysicalLocationController::class, 'show']);
        Route::post('/', [PhysicalLocationController::class, 'store']);
        Route::put('/{id}', [PhysicalLocationController::class, 'update']);
        Route::delete('/{id}', [PhysicalLocationController::class, 'destroy']);
        Route::get('/{id}/capacity', [PhysicalLocationController::class, 'checkCapacity']);
    });

    // Gestión de usuarios (con cache)
    Route::middleware('api.cache:600')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'show']);
    });

    // Gestión de categorías (con cache)
    Route::middleware('api.cache:1800')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
    });
    Route::get('categories/{id}', [CategoryController::class, 'show']);

    // Gestión de estados
    Route::get('statuses', [StatusController::class, 'index']);
    Route::get('statuses/{id}', [StatusController::class, 'show']);

    // Gestión de etiquetas
    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{id}', [TagController::class, 'show']);
    Route::get('tags/popular', [TagController::class, 'popular']);

    // Información de la empresa
    Route::prefix('companies')->group(function () {
        Route::get('current', [CompanyController::class, 'current']);
        Route::get('current/branches', [CompanyController::class, 'branches']);
        Route::get('current/departments', [CompanyController::class, 'departments']);
        Route::get('current/stats', [CompanyController::class, 'stats']);
    });

    // Búsqueda avanzada
    Route::prefix('search')->group(function () {
        Route::get('documents', [SearchController::class, 'searchDocuments']);
        Route::get('users', [SearchController::class, 'searchUsers']);
        Route::get('suggestions', [SearchController::class, 'suggestions']);
    });

    // Integración con hardware
    Route::prefix('hardware')->group(function () {
        Route::post('barcode/scan', [HardwareController::class, 'scanBarcode']);
        Route::post('qr/scan', [HardwareController::class, 'scanQRCode']);
        Route::get('scanners/status', [HardwareController::class, 'getScannersStatus']);
        Route::get('scan-history', [HardwareController::class, 'getScanHistory']);
    });

    // Sistema de webhooks
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index']);
        Route::post('register', [WebhookController::class, 'register']);
        Route::put('{id}', [WebhookController::class, 'update']);
        Route::delete('{id}', [WebhookController::class, 'destroy']);
        Route::post('{id}/test', [WebhookController::class, 'test']);
    });

    // Información del usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Ruta para generar documentación
Route::get('/docs', function () {
    return redirect('/api/documentation');
});
