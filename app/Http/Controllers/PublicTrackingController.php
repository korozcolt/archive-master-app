<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicTrackingController extends Controller
{
    /**
     * Mostrar formulario de búsqueda de tracking
     */
    public function index()
    {
        return view('tracking.index');
    }

    /**
     * Buscar documento por código de tracking público
     */
    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'required|string|size:32',
        ], [
            'tracking_code.required' => 'Debe ingresar un código de tracking',
            'tracking_code.size' => 'El código de tracking debe tener 32 caracteres',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $trackingCode = strtoupper(trim($request->tracking_code));

        // Buscar documento por código de tracking
        $document = Document::where('public_tracking_code', $trackingCode)
            ->where('tracking_enabled', true)
            ->with([
                'status',
                'category',
                'company',
                'workflowHistory' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'workflowHistory.fromStatus',
                'workflowHistory.toStatus',
                'workflowHistory.user',
            ])
            ->first();

        // Validar que el documento existe
        if (!$document) {
            Log::warning('Intento de tracking con código inválido', [
                'tracking_code' => $trackingCode,
                'ip' => $request->ip(),
            ]);

            return back()
                ->with('error', 'Código de tracking no válido o tracking no habilitado para este documento')
                ->withInput();
        }

        // Validar que el tracking no haya expirado
        if ($document->tracking_expires_at && Carbon::parse($document->tracking_expires_at)->isPast()) {
            Log::info('Intento de tracking con código expirado', [
                'tracking_code' => $trackingCode,
                'expired_at' => $document->tracking_expires_at,
                'ip' => $request->ip(),
            ]);

            return back()
                ->with('error', 'El código de tracking ha expirado')
                ->withInput();
        }

        // Registrar acceso al tracking
        Log::info('Tracking público consultado', [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'tracking_code' => $trackingCode,
            'ip' => $request->ip(),
        ]);

        // Preparar información pública (sin exponer datos sensibles)
        $publicData = [
            'tracking_code' => $document->public_tracking_code,
            'title' => $document->title,
            'description' => $document->description,
            'status' => $document->status ? [
                'name' => $document->status->name,
                'color' => $document->status->color ?? 'gray',
            ] : null,
            'category' => $document->category ? $document->category->name : null,
            'company' => $document->company ? $document->company->name : null,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
            'tracking_expires_at' => $document->tracking_expires_at,
            'workflow_history' => $document->workflowHistory->map(function ($history) {
                return [
                    'date' => $history->created_at,
                    'from_status' => $history->fromStatus ? $history->fromStatus->name : 'Inicial',
                    'to_status' => $history->toStatus ? $history->toStatus->name : 'Desconocido',
                    'comment' => $history->comment,
                    'user_name' => $history->user ? $history->user->name : 'Sistema',
                ];
            }),
        ];

        return view('tracking.show', [
            'document' => $publicData,
        ]);
    }

    /**
     * API endpoint para tracking (retorna JSON)
     */
    public function trackApi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'required|string|size:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Código de tracking inválido',
                'errors' => $validator->errors(),
            ], 422);
        }

        $trackingCode = strtoupper(trim($request->tracking_code));

        $document = Document::where('public_tracking_code', $trackingCode)
            ->where('tracking_enabled', true)
            ->with(['status', 'category', 'workflowHistory.fromStatus', 'workflowHistory.toStatus'])
            ->first();

        if (!$document) {
            Log::warning('API tracking con código inválido', [
                'tracking_code' => $trackingCode,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Código de tracking no válido o tracking no habilitado',
            ], 404);
        }

        if ($document->tracking_expires_at && Carbon::parse($document->tracking_expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'El código de tracking ha expirado',
                'expired_at' => $document->tracking_expires_at,
            ], 410); // 410 Gone
        }

        Log::info('API tracking consultado', [
            'document_id' => $document->id,
            'tracking_code' => $trackingCode,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'tracking_code' => $document->public_tracking_code,
                'title' => $document->title,
                'description' => $document->description,
                'status' => $document->status ? [
                    'name' => $document->status->name,
                    'color' => $document->status->color ?? 'gray',
                ] : null,
                'category' => $document->category ? $document->category->name : null,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
                'tracking_expires_at' => $document->tracking_expires_at,
                'timeline' => $document->workflowHistory->map(function ($history) {
                    return [
                        'date' => $history->created_at,
                        'from_status' => $history->fromStatus ? $history->fromStatus->name : 'Inicial',
                        'to_status' => $history->toStatus ? $history->toStatus->name : 'Desconocido',
                        'comment' => $history->comment,
                    ];
                }),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
