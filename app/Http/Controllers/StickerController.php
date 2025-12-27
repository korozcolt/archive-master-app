<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PhysicalLocation;
use App\Services\StickerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StickerController extends Controller
{
    protected StickerService $stickerService;

    public function __construct(StickerService $stickerService)
    {
        $this->stickerService = $stickerService;
        $this->middleware('auth');
    }

    /**
     * Preview sticker para un documento
     */
    public function previewDocument(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $html = $this->stickerService->previewDocument($document, $template, $options);

            return response($html)
                ->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            Log::error('Error previewing document sticker', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar la vista previa del sticker',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview sticker para una ubicación física
     */
    public function previewLocation(Request $request, PhysicalLocation $location)
    {
        $this->authorize('view', $location);

        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $html = $this->stickerService->previewLocation($location, $template, $options);

            return response($html)
                ->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            Log::error('Error previewing location sticker', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar la vista previa del sticker',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar sticker PDF para un documento
     */
    public function downloadDocument(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $pdf = $this->stickerService->generatePDFForDocument($document, $template, $options);

            $filename = "sticker-{$document->document_number}-" . date('Ymd-His') . ".pdf";

            Log::info('Sticker de documento generado', [
                'document_id' => $document->id,
                'document_number' => $document->document_number,
                'template' => $template,
                'user_id' => Auth::id(),
            ]);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            Log::error('Error generating document sticker PDF', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar el sticker PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar sticker PDF para una ubicación física
     */
    public function downloadLocation(Request $request, PhysicalLocation $location)
    {
        $this->authorize('view', $location);

        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $pdf = $this->stickerService->generatePDFForLocation($location, $template, $options);

            $filename = "sticker-location-{$location->code}-" . date('Ymd-His') . ".pdf";

            Log::info('Sticker de ubicación generado', [
                'location_id' => $location->id,
                'location_code' => $location->code,
                'template' => $template,
                'user_id' => Auth::id(),
            ]);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            Log::error('Error generating location sticker PDF', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar el sticker PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar batch de stickers para múltiples documentos
     */
    public function downloadBatchDocuments(Request $request)
    {
        $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
            'template' => 'nullable|string|in:standard,compact,detailed,label',
            'options' => 'nullable|array',
        ]);

        $documentIds = $request->input('document_ids');
        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $documents = Document::whereIn('id', $documentIds)
                ->where('company_id', Auth::user()->company_id)
                ->get();

            if ($documents->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron documentos válidos',
                ], 404);
            }

            $pdf = $this->stickerService->generateBatchForDocuments(
                $documents->all(),
                $template,
                $options
            );

            $filename = "stickers-batch-" . count($documents) . "-docs-" . date('Ymd-His') . ".pdf";

            Log::info('Batch de stickers de documentos generado', [
                'document_count' => count($documents),
                'template' => $template,
                'user_id' => Auth::id(),
            ]);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            Log::error('Error generating batch document stickers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar los stickers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar batch de stickers para múltiples ubicaciones
     */
    public function downloadBatchLocations(Request $request)
    {
        $request->validate([
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'exists:physical_locations,id',
            'template' => 'nullable|string|in:standard,compact,detailed,label',
            'options' => 'nullable|array',
        ]);

        $locationIds = $request->input('location_ids');
        $template = $request->input('template', 'standard');
        $options = $request->input('options', []);

        try {
            $locations = PhysicalLocation::whereIn('id', $locationIds)
                ->where('company_id', Auth::user()->company_id)
                ->get();

            if ($locations->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron ubicaciones válidas',
                ], 404);
            }

            $pdf = $this->stickerService->generateBatchForLocations(
                $locations->all(),
                $template,
                $options
            );

            $filename = "stickers-batch-" . count($locations) . "-locations-" . date('Ymd-His') . ".pdf";

            Log::info('Batch de stickers de ubicaciones generado', [
                'location_count' => count($locations),
                'template' => $template,
                'user_id' => Auth::id(),
            ]);

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            Log::error('Error generating batch location stickers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al generar los stickers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener templates disponibles
     */
    public function templates()
    {
        return response()->json([
            'templates' => $this->stickerService->getAvailableTemplates(),
            'default_options' => $this->stickerService->getDefaultOptions(),
        ]);
    }

    /**
     * Mostrar vista de configuración de sticker
     */
    public function configure(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        return view('stickers.configure', [
            'document' => $document,
            'templates' => $this->stickerService->getAvailableTemplates(),
            'defaultOptions' => $this->stickerService->getDefaultOptions(),
        ]);
    }

    /**
     * Mostrar vista de configuración de sticker para ubicación
     */
    public function configureLocation(Request $request, PhysicalLocation $location)
    {
        $this->authorize('view', $location);

        return view('stickers.configure-location', [
            'location' => $location,
            'templates' => $this->stickerService->getAvailableTemplates(),
            'defaultOptions' => $this->stickerService->getDefaultOptions(),
        ]);
    }
}
