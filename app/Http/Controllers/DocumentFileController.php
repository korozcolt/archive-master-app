<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentVersion;
use App\Services\DocumentFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentFileController extends Controller
{
    public function __construct(
        private readonly DocumentFileService $documentFileService
    ) {}

    public function preview(Request $request, int $id)
    {
        $document = Document::query()->findOrFail($id);

        $this->authorizeAccess($request, $document);
        $this->ensureFileExists($document->file_path);
        $this->logAccess($request, $document, 'preview');

        return $this->documentFileService->inlineResponse($document->file_path);
    }

    public function download(Request $request, int $id)
    {
        $document = Document::query()->findOrFail($id);

        $this->authorizeAccess($request, $document);
        $this->ensureFileExists($document->file_path);
        $this->logDownload($request, $document);

        return $this->documentFileService->downloadResponse($document->file_path);
    }

    public function downloadVersion(Request $request, int $id)
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($id);
        $document = $version->document;

        abort_if($document === null, 404);

        $this->authorizeAccess($request, $document);
        $this->ensureFileExists($version->file_path);
        $this->logDownload($request, $document, $version->id);

        return $this->documentFileService->downloadResponse($version->file_path);
    }

    private function authorizeAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        abort_if($user === null, 403, 'No tiene permiso para acceder a este documento.');

        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_if(
            (int) $user->company_id !== (int) $document->company_id,
            403,
            'No tiene permiso para acceder a este documento.'
        );

        abort_unless(
            $document->canBeAccessedByPortalUser($user),
            403,
            'No tiene permiso para acceder a este documento.'
        );
    }

    private function ensureFileExists(?string $filePath): void
    {
        abort_if(
            ! $filePath || ! $this->documentFileService->fileExists($filePath),
            404,
            'El archivo no existe.'
        );
    }

    private function logDownload(Request $request, Document $document, ?int $versionId = null): void
    {
        Log::info('Descarga de documento', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'version_id' => $versionId,
            'user_id' => $request->user()?->id,
            'company_id' => $request->user()?->company_id,
        ]);

        $this->logAccess($request, $document, 'download');
    }

    private function logAccess(Request $request, Document $document, string $action): void
    {
        try {
            DocumentAccessLog::query()->create([
                'document_id' => $document->id,
                'user_id' => $request->user()?->id,
                'action' => $action,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar acceso al documento', [
                'document_id' => $document->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
