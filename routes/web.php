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

// Rutas de notificaciones
Route::middleware(['auth'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [App\Http\Controllers\NotificationController::class, 'index'])->name('index');
    Route::get('/unread', [App\Http\Controllers\NotificationController::class, 'unread'])->name('unread');
    Route::post('/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('markAsRead');
    Route::post('/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('markAllAsRead');
    Route::delete('/{id}', [App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
    Route::delete('/clear/read', [App\Http\Controllers\NotificationController::class, 'clearRead'])->name('clearRead');
});

// Rutas de aprobaciones
Route::middleware(['auth'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [App\Http\Controllers\ApprovalController::class, 'index'])->name('index');
    Route::get('/document/{document}', [App\Http\Controllers\ApprovalController::class, 'show'])->name('show');
    Route::get('/document/{document}/history', [App\Http\Controllers\ApprovalController::class, 'history'])->name('history');
    Route::post('/{approval}/approve', [App\Http\Controllers\ApprovalController::class, 'approve'])->name('approve');
    Route::post('/{approval}/reject', [App\Http\Controllers\ApprovalController::class, 'reject'])->name('reject');
});

// Grupo de rutas para documentos de usuarios
Route::middleware(['auth'])->group(function () {
    // Ruta de exportación (debe estar antes del resource)
    Route::get('/documents/export/csv', [App\Http\Controllers\UserDocumentController::class, 'export'])->name('documents.export');

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
