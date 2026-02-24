<?php

use App\Http\Middleware\RedirectBasedOnRole;
use App\Livewire\Portal\Dashboard as PortalDashboard;
use App\Livewire\Portal\Reports as PortalReports;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Ruta principal - Selector de acceso Portal/Admin
Route::get('/', function () {
    return view('auth.entry-selector');
})->name('welcome');

Route::get('/login', [App\Http\Controllers\PortalAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\PortalAuthController::class, 'passwordLogin'])->name('portal.auth.password-login');
Route::post('/portal/auth/request-otp', [App\Http\Controllers\PortalAuthController::class, 'requestOtp'])->name('portal.auth.request-otp');
Route::get('/portal/auth/verify', [App\Http\Controllers\PortalAuthController::class, 'showVerifyForm'])->name('portal.auth.verify.form');
Route::post('/portal/auth/verify', [App\Http\Controllers\PortalAuthController::class, 'verifyOtp'])->name('portal.auth.verify');

// Rutas públicas de tracking (sin autenticación)
Route::prefix('tracking')->name('tracking.')->group(function () {
    Route::get('/', [App\Http\Controllers\PublicTrackingController::class, 'index'])->name('index');
    Route::post('/track', [App\Http\Controllers\PublicTrackingController::class, 'track'])->name('track');
    Route::get('/api/track', [App\Http\Controllers\PublicTrackingController::class, 'trackApi'])->name('api');
});

// Dashboard - Redirige automáticamente según el rol del usuario
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Portal de usuarios (no admin)
Route::middleware(['auth', RedirectBasedOnRole::class])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', PortalDashboard::class)->name('dashboard');
        Route::get('/reports', PortalReports::class)->name('reports');
    });

// Debug route for testing
Route::get('/debug-user', function () {
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
Route::post('/logout', function () {
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
    Route::middleware('throttle:ai-actions')->group(function () {
        Route::post('/documents/{document}/ai/regenerate', [App\Http\Controllers\UserDocumentController::class, 'regenerateAiSummary'])->name('documents.ai.regenerate');
        Route::post('/documents/{document}/ai/apply-suggestions', [App\Http\Controllers\UserDocumentController::class, 'applyAiSuggestions'])->name('documents.ai.apply');
        Route::post('/documents/{document}/ai/mark-incorrect', [App\Http\Controllers\UserDocumentController::class, 'markAiSummaryIncorrect'])->name('documents.ai.mark-incorrect');
    });

    Route::post('/documents/upload-drafts/temp-file', [App\Http\Controllers\UserDocumentController::class, 'uploadDraftTempFile'])
        ->name('documents.upload-drafts.temp-file');
    Route::post('/documents/upload-drafts/save', [App\Http\Controllers\UserDocumentController::class, 'saveUploadDraft'])
        ->name('documents.upload-drafts.save');
    Route::delete('/documents/upload-drafts/{draft}/items/{item}', [App\Http\Controllers\UserDocumentController::class, 'deleteUploadDraftItem'])
        ->name('documents.upload-drafts.items.destroy');
    Route::post('/documents/{document}/distributions', [App\Http\Controllers\UserDocumentController::class, 'sendToDepartments'])
        ->name('documents.distributions.store');
    Route::post('/documents/{document}/distribution-targets/{target}', [App\Http\Controllers\UserDocumentController::class, 'updateDistributionTarget'])
        ->name('documents.distribution-targets.update');
    Route::post('/documents/{document}/archive-location', [App\Http\Controllers\UserDocumentController::class, 'updateArchiveLocation'])
        ->name('documents.archive-location.update');

    // Rutas CRUD de documentos
    Route::resource('documents', App\Http\Controllers\UserDocumentController::class);
});

// Grupo de rutas adicionales para documentos
Route::prefix('documents')->name('documents.')->middleware(['auth'])->group(function () {
    Route::get('/{id}/preview', function ($id) {
        $document = Document::findOrFail($id);
        authorizeDocumentAccess($document);
        validateFileExists($document->file_path);

        if (function_exists('logDocumentAccess')) {
            logDocumentAccess($document, 'preview');
        }

        return app(\App\Services\DocumentFileService::class)->inlineResponse($document->file_path);
    })->name('preview');

    // Descarga de documento principal
    Route::get('/{id}/download', function ($id) {
        $document = Document::findOrFail($id);
        authorizeDocumentAccess($document);
        validateFileExists($document->file_path);

        logDocumentDownload($document);

        return downloadFile($document->file_path);
    })->name('download');

    // Grupo para versiones de documentos
    Route::prefix('versions')->name('versions.')->group(function () {
        // Descarga de versión específica
        Route::get('/{id}/download', function ($id) {
            $version = DocumentVersion::findOrFail($id);
            authorizeDocumentAccess($version->document);
            validateFileExists($version->file_path);

            logDocumentDownload($version->document, $version->id);

            return downloadFile($version->file_path);
        })->name('download');
    });
});

// Funciones auxiliares para mejorar legibilidad
if (! function_exists('authorizeDocumentAccess')) {
    function authorizeDocumentAccess($document): void
    {
        if (! Auth::check()) {
            abort(403, 'No tiene permiso para acceder a este documento.');
        }

        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return;
        }

        if ($user->company_id != $document->company_id) {
            abort(403, 'No tiene permiso para acceder a este documento.');
        }

        $hasAccess = canDownloadDocument($user, $document);

        if (! $hasAccess) {
            abort(403, 'No tiene permiso para acceder a este documento.');
        }
    }
}

if (! function_exists('canDownloadDocument')) {
    function canDownloadDocument($user, $document): bool
    {
        if ($user->hasRole(['admin'])) {
            return true;
        }

        if ($user->hasRole('branch_admin')) {
            return $document->branch_id === null || $document->branch_id === $user->branch_id;
        }

        if ($user->hasRole('office_manager')) {
            return $document->department_id === $user->department_id;
        }

        if ($user->hasRole('archive_manager')) {
            return true;
        }

        return $document->created_by === $user->id || $document->assigned_to === $user->id;
    }
}

if (! function_exists('validateFileExists')) {
    function validateFileExists($filePath): void
    {
        $service = app(\App\Services\DocumentFileService::class);
        if (! $filePath || ! $service->fileExists($filePath)) {
            abort(404, 'El archivo no existe.');
        }
    }
}

if (! function_exists('logDocumentDownload')) {
    function logDocumentDownload($document, ?int $versionId = null): void
    {
        \Log::info('Descarga de documento', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'version_id' => $versionId,
            'user_id' => Auth::id(),
            'company_id' => Auth::user()?->company_id,
        ]);

        logDocumentAccess($document, 'download');
    }
}

if (! function_exists('logDocumentAccess')) {
    function logDocumentAccess($document, string $action): void
    {
        try {
            \App\Models\DocumentAccessLog::create([
                'document_id' => $document->id,
                'user_id' => Auth::id(),
                'action' => $action,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo registrar acceso al documento', [
                'document_id' => $document->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (! function_exists('downloadFile')) {
    function downloadFile($filePath)
    {
        $service = app(\App\Services\DocumentFileService::class);

        return $service->downloadResponse($filePath);
    }
}

// Rutas de stickers (etiquetas con códigos de barras y QR)
Route::middleware(['auth'])->prefix('stickers')->name('stickers.')->group(function () {
    // Templates disponibles
    Route::get('/templates', [App\Http\Controllers\StickerController::class, 'templates'])->name('templates');

    // Documentos
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/{document}/preview', [App\Http\Controllers\StickerController::class, 'previewDocument'])->name('preview');
        Route::get('/{document}/download', [App\Http\Controllers\StickerController::class, 'downloadDocument'])->name('download');
        Route::get('/{document}/configure', [App\Http\Controllers\StickerController::class, 'configure'])->name('configure');
        Route::post('/batch/download', [App\Http\Controllers\StickerController::class, 'downloadBatchDocuments'])->name('batch.download');
    });

    // Ubicaciones físicas
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/{location}/preview', [App\Http\Controllers\StickerController::class, 'previewLocation'])->name('preview');
        Route::get('/{location}/download', [App\Http\Controllers\StickerController::class, 'downloadLocation'])->name('download');
        Route::get('/{location}/configure', [App\Http\Controllers\StickerController::class, 'configureLocation'])->name('configure');
        Route::post('/batch/download', [App\Http\Controllers\StickerController::class, 'downloadBatchLocations'])->name('batch.download');
    });
});

Route::middleware(['auth'])->prefix('receipts')->name('receipts.')->group(function () {
    Route::get('/{receipt}', [App\Http\Controllers\ReceiptController::class, 'show'])->name('show');
    Route::get('/{receipt}/download', [App\Http\Controllers\ReceiptController::class, 'download'])->name('download');
});
