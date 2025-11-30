<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Models\Document;
use App\Models\DocumentVersion;

// Ruta principal - Página de bienvenida con React
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Dashboard - Redirige automáticamente según el rol del usuario
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Debug route for testing
Route::get('/debug-user', function() {
    $user = Auth::user();
    return response()->json([
        'id' => $user->id,
        'email' => $user->email,
        'roles' => $user->roles->pluck('name'),
        'has_admin' => $user->hasRole('admin'),
        'has_regular_user' => $user->hasRole('regular_user'),
        'has_any_admin' => $user->hasAnyRole(['admin', 'super_admin', 'branch_admin', 'office_manager']),
    ]);
})->middleware(['auth']);

// Ruta de logout
Route::post('/logout', function() {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Grupo de rutas para documentos de usuarios
Route::middleware(['auth'])->group(function () {
    // Rutas CRUD de documentos
    Route::resource('documents', App\Http\Controllers\UserDocumentController::class);
});

// Grupo de rutas adicionales para documentos
Route::prefix('documents')->name('documents.')->middleware(['auth'])->group(function () {

    // Descarga de documento principal
    Route::get('/{id}/download', function ($id) {
        $document = Document::findOrFail($id);
        authorizeDocumentAccess($document);
        validateFileExists($document->file);

        return downloadFile($document->file);
    })->name('download');

    // Grupo para versiones de documentos
    Route::prefix('versions')->name('versions.')->group(function () {
        // Descarga de versión específica
        Route::get('/{id}/download', function ($id) {
            $version = DocumentVersion::findOrFail($id);
            authorizeDocumentAccess($version->document);
            validateFileExists($version->file_path);

            return downloadFile($version->file_path);
        })->name('download');
    });
});

// Funciones auxiliares para mejorar legibilidad
if (!function_exists('authorizeDocumentAccess')) {
    function authorizeDocumentAccess($document): void
    {
        if (!Auth::check() || (!Auth::user()->hasRole('super_admin') &&
            Auth::user()->company_id != $document->company_id)) {
            abort(403, 'No tiene permiso para acceder a este documento.');
        }
    }
}

if (!function_exists('validateFileExists')) {
    function validateFileExists($filePath): void
    {
        if (!$filePath || !file_exists(storage_path('app/public/' . $filePath))) {
            abort(404, 'El archivo no existe.');
        }
    }
}

if (!function_exists('downloadFile')) {
    function downloadFile($filePath)
    {
        return response()->download(
            storage_path('app/public/' . $filePath),
            basename($filePath)
        );
    }
}
