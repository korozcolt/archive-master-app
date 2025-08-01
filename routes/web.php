<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Models\Document;
use App\Models\DocumentVersion;

// Grupo de rutas para documentos
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
