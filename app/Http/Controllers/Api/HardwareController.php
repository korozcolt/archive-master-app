<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HardwareController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/api/hardware/barcode/scan",
     *     tags={"Hardware"},
     *     summary="Escanear código de barras",
     *     description="Procesar lectura de código de barras y obtener información del documento",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"barcode"},
     *             @OA\Property(property="barcode", type="string", example="DOCABC20250101120000A1B2XYZ123", description="Código de barras escaneado"),
     *             @OA\Property(property="scanner_id", type="string", example="SCANNER_001", description="ID del escáner utilizado"),
     *             @OA\Property(property="location", type="string", example="Recepción Principal", description="Ubicación donde se realizó el escaneo"),
     *             @OA\Property(property="action", type="string", enum={"view","checkout","checkin","verify"}, example="view", description="Acción a realizar con el documento")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento encontrado"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="document", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                     @OA\Property(property="title", type="string", example="Contrato de Servicios"),
     *                     @OA\Property(property="status", type="object"),
     *                     @OA\Property(property="category", type="object"),
     *                     @OA\Property(property="physical_location", type="string", example="Archivo Central - Estante A1")
     *                 ),
     *                 @OA\Property(property="scan_log", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="scanned_at", type="string", format="date-time"),
     *                     @OA\Property(property="scanner_id", type="string", example="SCANNER_001"),
     *                     @OA\Property(property="location", type="string", example="Recepción Principal"),
     *                     @OA\Property(property="action", type="string", example="view")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Documento no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Documento no encontrado con el código de barras proporcionado"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Errores de validación"),
     *             @OA\Property(property="errors", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function scanBarcode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:255',
            'scanner_id' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'action' => 'nullable|in:view,checkout,checkin,verify',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $barcode = $request->barcode;
        $scannerId = $request->get('scanner_id', 'UNKNOWN');
        $location = $request->get('location', 'Unknown Location');
        $action = $request->get('action', 'view');

        // Buscar documento por código de barras
        $document = Document::where('barcode', $barcode)
            ->where('company_id', Auth::user()->company_id)
            ->with(['status', 'category', 'creator', 'assignee', 'company'])
            ->first();

        if (!$document) {
            // Log del intento de escaneo fallido
            Log::warning('Barcode scan failed - Document not found', [
                'barcode' => $barcode,
                'scanner_id' => $scannerId,
                'location' => $location,
                'user_id' => Auth::id(),
                'company_id' => Auth::user()->company_id,
            ]);

            return $this->errorResponse('Documento no encontrado con el código de barras proporcionado', 404);
        }

        // Registrar el escaneo
        $scanLog = $this->logBarcodeScanning($document, $scannerId, $location, $action);

        // Procesar acción específica
        $actionResult = $this->processHardwareAction($document, $action, $request);

        return $this->successResponse([
            'document' => [
                'id' => $document->id,
                'document_number' => $document->document_number,
                'title' => $document->title,
                'description' => $document->description,
                'status' => $document->status,
                'category' => $document->category,
                'physical_location' => $document->physical_location,
                'is_confidential' => $document->is_confidential,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ],
            'scan_log' => $scanLog,
            'action_result' => $actionResult,
        ], 'Documento encontrado y procesado');
    }

    /**
     * @OA\Post(
     *     path="/api/hardware/qr/scan",
     *     tags={"Hardware"},
     *     summary="Escanear código QR",
     *     description="Procesar lectura de código QR y obtener información del documento",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"qr_data"},
     *             @OA\Property(property="qr_data", type="string", example="{""id"":1,""document_number"":""DOC-ABC-20250101120000-A1B2""}", description="Datos del código QR escaneado"),
     *             @OA\Property(property="scanner_id", type="string", example="QR_READER_001", description="ID del lector QR utilizado"),
     *             @OA\Property(property="location", type="string", example="Archivo Central", description="Ubicación donde se realizó el escaneo"),
     *             @OA\Property(property="action", type="string", enum={"view","checkout","checkin","verify"}, example="view", description="Acción a realizar con el documento")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Código QR procesado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Código QR procesado exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="document", type="object"),
     *                 @OA\Property(property="qr_data_parsed", type="object"),
     *                 @OA\Property(property="scan_log", type="object")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Código QR inválido",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Código QR inválido o corrupto"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function scanQRCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string',
            'scanner_id' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'action' => 'nullable|in:view,checkout,checkin,verify',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $qrData = $request->qr_data;
        $scannerId = $request->get('scanner_id', 'QR_READER_UNKNOWN');
        $location = $request->get('location', 'Unknown Location');
        $action = $request->get('action', 'view');

        // Decodificar datos del QR
        $qrParsed = json_decode($qrData, true);

        if (!$qrParsed || !isset($qrParsed['id']) || !isset($qrParsed['document_number'])) {
            return $this->errorResponse('Código QR inválido o corrupto', 400);
        }

        // Buscar documento
        $document = Document::where('id', $qrParsed['id'])
            ->where('document_number', $qrParsed['document_number'])
            ->where('company_id', Auth::user()->company_id)
            ->with(['status', 'category', 'creator', 'assignee', 'company'])
            ->first();

        if (!$document) {
            Log::warning('QR scan failed - Document not found', [
                'qr_data' => $qrData,
                'parsed_data' => $qrParsed,
                'scanner_id' => $scannerId,
                'location' => $location,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse('Documento no encontrado', 404);
        }

        // Registrar el escaneo QR
        $scanLog = $this->logQRScanning($document, $scannerId, $location, $action, $qrData);

        // Procesar acción específica
        $actionResult = $this->processHardwareAction($document, $action, $request);

        return $this->successResponse([
            'document' => $document,
            'qr_data_parsed' => $qrParsed,
            'scan_log' => $scanLog,
            'action_result' => $actionResult,
        ], 'Código QR procesado exitosamente');
    }

    /**
     * @OA\Get(
     *     path="/api/hardware/scanners/status",
     *     tags={"Hardware"},
     *     summary="Estado de escáneres",
     *     description="Obtener estado y estadísticas de los escáneres registrados",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estado de escáneres",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="scanners",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="scanner_id", type="string", example="SCANNER_001"),
     *                         @OA\Property(property="location", type="string", example="Recepción Principal"),
     *                         @OA\Property(property="last_scan", type="string", format="date-time"),
     *                         @OA\Property(property="total_scans_today", type="integer", example=25),
     *                         @OA\Property(property="status", type="string", enum={"online","offline","maintenance"}, example="online")
     *                     )
     *                 ),
     *                 @OA\Property(property="total_scans_today", type="integer", example=150),
     *                 @OA\Property(property="active_scanners", type="integer", example=3),
     *                 @OA\Property(property="last_updated", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function getScannersStatus(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $today = Carbon::today();

        // Simular datos de escáneres (en producción esto vendría de una tabla de escáneres)
        $scanners = [
            [
                'scanner_id' => 'SCANNER_001',
                'location' => 'Recepción Principal',
                'last_scan' => now()->subMinutes(15),
                'total_scans_today' => 25,
                'status' => 'online'
            ],
            [
                'scanner_id' => 'QR_READER_001',
                'location' => 'Archivo Central',
                'last_scan' => now()->subHours(2),
                'total_scans_today' => 18,
                'status' => 'online'
            ],
            [
                'scanner_id' => 'MOBILE_SCANNER_001',
                'location' => 'Móvil - Departamento Legal',
                'last_scan' => now()->subMinutes(5),
                'total_scans_today' => 12,
                'status' => 'online'
            ]
        ];

        $totalScansToday = array_sum(array_column($scanners, 'total_scans_today'));
        $activeScanners = count(array_filter($scanners, fn($s) => $s['status'] === 'online'));

        return $this->successResponse([
            'scanners' => $scanners,
            'total_scans_today' => $totalScansToday,
            'active_scanners' => $activeScanners,
            'last_updated' => now(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/hardware/scan-history",
     *     tags={"Hardware"},
     *     summary="Historial de escaneos",
     *     description="Obtener historial de escaneos de códigos de barras y QR",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="document_id",
     *         in="query",
     *         description="Filtrar por documento específico",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="scanner_id",
     *         in="query",
     *         description="Filtrar por escáner específico",
     *         required=false,
     *         @OA\Schema(type="string", example="SCANNER_001")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Fecha desde (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha hasta (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historial de escaneos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="document_id", type="integer", example=1),
     *                     @OA\Property(property="document_number", type="string", example="DOC-ABC-20250101120000-A1B2"),
     *                     @OA\Property(property="scanner_id", type="string", example="SCANNER_001"),
     *                     @OA\Property(property="scan_type", type="string", enum={"barcode","qr"}, example="barcode"),
     *                     @OA\Property(property="location", type="string", example="Recepción Principal"),
     *                     @OA\Property(property="action", type="string", example="view"),
     *                     @OA\Property(property="user_name", type="string", example="Juan Pérez"),
     *                     @OA\Property(property="scanned_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function getScanHistory(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        // En producción, esto consultaría una tabla de logs de escaneo
        // Por ahora simulamos algunos datos
        $scanHistory = collect([
            [
                'id' => 1,
                'document_id' => 1,
                'document_number' => 'DOC-ABC-20250101120000-A1B2',
                'scanner_id' => 'SCANNER_001',
                'scan_type' => 'barcode',
                'location' => 'Recepción Principal',
                'action' => 'view',
                'user_name' => 'Juan Pérez',
                'scanned_at' => now()->subMinutes(15),
            ],
            [
                'id' => 2,
                'document_id' => 2,
                'document_number' => 'DOC-XYZ-20250101130000-B2C3',
                'scanner_id' => 'QR_READER_001',
                'scan_type' => 'qr',
                'location' => 'Archivo Central',
                'action' => 'checkout',
                'user_name' => 'María García',
                'scanned_at' => now()->subHours(1),
            ],
        ]);

        // Aplicar filtros simulados
        if ($request->has('document_id')) {
            $scanHistory = $scanHistory->where('document_id', $request->document_id);
        }

        if ($request->has('scanner_id')) {
            $scanHistory = $scanHistory->where('scanner_id', $request->scanner_id);
        }

        return $this->successResponse($scanHistory->values()->all());
    }

    /**
     * Registrar escaneo de código de barras
     */
    private function logBarcodeScanning(Document $document, string $scannerId, string $location, string $action): array
    {
        $logData = [
            'id' => rand(1000, 9999), // En producción sería un ID real de base de datos
            'document_id' => $document->id,
            'scanner_id' => $scannerId,
            'scan_type' => 'barcode',
            'location' => $location,
            'action' => $action,
            'user_id' => Auth::id(),
            'scanned_at' => now(),
        ];

        // Log para auditoría
        Log::info('Barcode scanned successfully', array_merge($logData, [
            'document_number' => $document->document_number,
            'company_id' => $document->company_id,
        ]));

        return $logData;
    }

    /**
     * Registrar escaneo de código QR
     */
    private function logQRScanning(Document $document, string $scannerId, string $location, string $action, string $qrData): array
    {
        $logData = [
            'id' => rand(1000, 9999), // En producción sería un ID real de base de datos
            'document_id' => $document->id,
            'scanner_id' => $scannerId,
            'scan_type' => 'qr',
            'location' => $location,
            'action' => $action,
            'qr_data' => $qrData,
            'user_id' => Auth::id(),
            'scanned_at' => now(),
        ];

        // Log para auditoría
        Log::info('QR code scanned successfully', array_merge($logData, [
            'document_number' => $document->document_number,
            'company_id' => $document->company_id,
        ]));

        return $logData;
    }

    /**
     * Procesar acción específica del hardware
     */
    private function processHardwareAction(Document $document, string $action, Request $request): array
    {
        $result = ['action' => $action, 'success' => true, 'message' => ''];

        switch ($action) {
            case 'view':
                $result['message'] = 'Documento visualizado';
                break;

            case 'checkout':
                // Simular checkout del documento
                $result['message'] = 'Documento retirado del archivo';
                $result['checkout_time'] = now();
                $result['checkout_user'] = Auth::user()->name;
                break;

            case 'checkin':
                // Simular checkin del documento
                $result['message'] = 'Documento devuelto al archivo';
                $result['checkin_time'] = now();
                $result['checkin_user'] = Auth::user()->name;
                break;

            case 'verify':
                // Verificar integridad del documento
                $result['message'] = 'Documento verificado';
                $result['verification_status'] = 'valid';
                $result['last_modified'] = $document->updated_at;
                break;

            default:
                $result['message'] = 'Acción procesada';
        }

        return $result;
    }
}
