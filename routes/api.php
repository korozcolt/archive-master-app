<?php

use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Search API Routes
Route::prefix('search')->group(function () {
    Route::get('/documents', [SearchController::class, 'documents']);
    Route::get('/users', [SearchController::class, 'users']);
    Route::get('/companies', [SearchController::class, 'companies']);
    Route::get('/global', [SearchController::class, 'global']);
    Route::get('/suggestions', [SearchController::class, 'suggestions']);
    Route::get('/statistics', [SearchController::class, 'statistics']);
});
