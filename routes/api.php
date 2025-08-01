<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TagController;

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

// Authentication Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/tokens', [AuthController::class, 'tokens']);
        Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);
    });
});

// Protected API Routes
Route::middleware('auth:sanctum')->group(function () {
    
    // User info endpoint (legacy compatibility)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Resource API Routes
    Route::apiResource('documents', DocumentController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('statuses', StatusController::class);
    Route::apiResource('tags', TagController::class);
    
    // Additional User Routes
    Route::get('/users/me', [UserController::class, 'me']);
    
    // Search API Routes
    Route::prefix('search')->group(function () {
        Route::get('/documents', [SearchController::class, 'searchDocuments']);
        Route::get('/users', [SearchController::class, 'searchUsers']);
        Route::get('/companies', [SearchController::class, 'searchCompanies']);
        Route::get('/global', [SearchController::class, 'globalSearch']);
        Route::get('/suggestions', [SearchController::class, 'searchSuggestions']);
        Route::get('/statistics', [SearchController::class, 'searchStatistics']);
    });
});
