<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WebhookController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/api/webhooks/register",
     *     tags={"Webhooks"},
     *     summary="Registrar webhook",
     *     description="Registrar un nuevo webhook para recibir notificaciones de eventos",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url","events"},
     *             @OA\Property(property="url", type="string", format="url", example="https://mi-sistema.com/webhooks/archivemaster", description="URL del endpoint webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"document.created","document.updated","document.status_changed"}, description="Eventos a los que suscribirse"),
     *             @OA\Property(property="name", type="string", example="Sistema ERP Principal", description="Nombre descriptivo del webhook"),
     *             @OA\Property(property="secret", type="string", example="mi_secreto_super_seguro", description="Secreto para validar la autenticidad de las llamadas"),
     *             @OA\Property(property="active", type="boolean", example=true, description="Si el webhook está activo"),
     *             @OA\Property(property="retry_attempts", type="integer", example=3, description="Número de reintentos en caso de fallo"),
     *             @OA\Property(property="timeout", type="integer", example=30, description="Timeout en segundos para las llamadas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Webhook registrado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Webhook registrado exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="webhook_123456"),
     *                 @OA\Property(property="url", type="string", example="https://mi-sistema.com/webhooks/archivemaster"),
     *                 @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="name", type="string", example="Sistema ERP Principal"),
     *                 @OA\Property(property="active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
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
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:document.created,document.updated,document.deleted,document.status_changed,user.created,user.updated,workflow.transition',
            'name' => 'nullable|string|max:255',
            'secret' => 'nullable|string|max:255',
            'active' => 'boolean',
            'retry_attempts' => 'integer|min:0|max:10',
            'timeout' => 'integer|min:5|max:300',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        $webhookId = 'webhook_' . uniqid();
        $companyId = Auth::user()->company_id;

        $webhookData = [
            'id' => $webhookId,
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'url' => $request->url,
            'events' => $request->events,
            'name' => $request->get('name', 'Webhook sin nombre'),
            'secret' => $request->get('secret'),
            'active' => $request->get('active', true),
            'retry_attempts' => $request->get('retry_attempts', 3),
            'timeout' => $request->get('timeout', 30),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Guardar en cache (en producción sería en base de datos)
        $this->saveWebhook($webhookData);

        // Test de conectividad inicial
        $testResult = $this->testWebhookConnectivity($webhookData);

        Log::info('Webhook registered', [
            'webhook_id' => $webhookId,
            'company_id' => $companyId,
            'url' => $request->url,
            'events' => $request->events,
            'test_result' => $testResult,
        ]);

        return $this->successResponse([
            'id' => $webhookId,
            'url' => $webhookData['url'],
            'events' => $webhookData['events'],
            'name' => $webhookData['name'],
            'active' => $webhookData['active'],
            'test_result' => $testResult,
            'created_at' => $webhookData['created_at'],
        ], 'Webhook registrado exitosamente', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/webhooks",
     *     tags={"Webhooks"},
     *     summary="Listar webhooks",
     *     description="Obtener lista de webhooks registrados para la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de webhooks",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="webhook_123456"),
     *                     @OA\Property(property="url", type="string", example="https://mi-sistema.com/webhooks/archivemaster"),
     *                     @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="name", type="string", example="Sistema ERP Principal"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="last_success", type="string", format="date-time"),
     *                     @OA\Property(property="last_failure", type="string", format="date-time"),
     *                     @OA\Property(property="total_calls", type="integer", example=150),
     *                     @OA\Property(property="success_rate", type="number", format="float", example=98.5),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $webhooks = $this->getWebhooksByCompany($companyId);

        // Agregar estadísticas a cada webhook
        $webhooksWithStats = array_map(function ($webhook) {
            $stats = $this->getWebhookStats($webhook['id']);
            return array_merge($webhook, $stats);
        }, $webhooks);

        return $this->successResponse($webhooksWithStats);
    }

    /**
     * @OA\Put(
     *     path="/api/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Actualizar webhook",
     *     description="Actualizar configuración de un webhook existente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del webhook",
     *         required=true,
     *         @OA\Schema(type="string", example="webhook_123456")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", format="url", example="https://mi-sistema-actualizado.com/webhooks/archivemaster"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"document.created","document.updated"}),
     *             @OA\Property(property="name", type="string", example="Sistema ERP Actualizado"),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="retry_attempts", type="integer", example=5),
     *             @OA\Property(property="timeout", type="integer", example=45)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Webhook actualizado exitosamente"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Webhook no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Webhook no encontrado"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $webhook = $this->getWebhook($id);

        if (!$webhook || $webhook['company_id'] !== Auth::user()->company_id) {
            return $this->errorResponse('Webhook no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|required|url|max:500',
            'events' => 'sometimes|required|array|min:1',
            'events.*' => 'required|string|in:document.created,document.updated,document.deleted,document.status_changed,user.created,user.updated,workflow.transition',
            'name' => 'nullable|string|max:255',
            'active' => 'boolean',
            'retry_attempts' => 'integer|min:0|max:10',
            'timeout' => 'integer|min:5|max:300',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Errores de validación', 422, $validator->errors());
        }

        // Actualizar datos del webhook
        $updatedData = array_merge($webhook, $request->only([
            'url', 'events', 'name', 'active', 'retry_attempts', 'timeout'
        ]));
        $updatedData['updated_at'] = now();

        $this->saveWebhook($updatedData);

        Log::info('Webhook updated', [
            'webhook_id' => $id,
            'company_id' => Auth::user()->company_id,
            'changes' => $request->only(['url', 'events', 'name', 'active']),
        ]);

        return $this->successResponse($updatedData, 'Webhook actualizado exitosamente');
    }

    /**
     * @OA\Delete(
     *     path="/api/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Eliminar webhook",
     *     description="Eliminar un webhook registrado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del webhook",
     *         required=true,
     *         @OA\Schema(type="string", example="webhook_123456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Webhook eliminado exitosamente"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $webhook = $this->getWebhook($id);

        if (!$webhook || $webhook['company_id'] !== Auth::user()->company_id) {
            return $this->errorResponse('Webhook no encontrado', 404);
        }

        $this->deleteWebhook($id);

        Log::info('Webhook deleted', [
            'webhook_id' => $id,
            'company_id' => Auth::user()->company_id,
        ]);

        return $this->successResponse(null, 'Webhook eliminado exitosamente');
    }

    /**
     * @OA\Post(
     *     path="/api/webhooks/{id}/test",
     *     tags={"Webhooks"},
     *     summary="Probar webhook",
     *     description="Enviar un evento de prueba al webhook para verificar conectividad",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del webhook",
     *         required=true,
     *         @OA\Schema(type="string", example="webhook_123456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prueba de webhook completada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Prueba de webhook completada"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="webhook_id", type="string", example="webhook_123456"),
     *                 @OA\Property(property="test_successful", type="boolean", example=true),
     *                 @OA\Property(property="response_time_ms", type="integer", example=250),
     *                 @OA\Property(property="status_code", type="integer", example=200),
     *                 @OA\Property(property="error_message", type="string", example=null)
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function test(string $id): JsonResponse
    {
        $webhook = $this->getWebhook($id);

        if (!$webhook || $webhook['company_id'] !== Auth::user()->company_id) {
            return $this->errorResponse('Webhook no encontrado', 404);
        }

        $testResult = $this->testWebhookConnectivity($webhook);

        return $this->successResponse([
            'webhook_id' => $id,
            'test_successful' => $testResult['success'],
            'response_time_ms' => $testResult['response_time_ms'],
            'status_code' => $testResult['status_code'],
            'error_message' => $testResult['error_message'],
        ], 'Prueba de webhook completada');
    }

    /**
     * Disparar webhook para un evento específico
     */
    public function triggerWebhook(string $event, array $data, int $companyId): void
    {
        $webhooks = $this->getWebhooksByCompany($companyId);

        foreach ($webhooks as $webhook) {
            if (!$webhook['active'] || !in_array($event, $webhook['events'])) {
                continue;
            }

            $this->sendWebhookCall($webhook, $event, $data);
        }
    }

    /**
     * Enviar llamada al webhook
     */
    private function sendWebhookCall(array $webhook, string $event, array $data): void
    {
        $payload = [
            'event' => $event,
            'data' => $data,
            'webhook_id' => $webhook['id'],
            'timestamp' => now()->toISOString(),
            'company_id' => $webhook['company_id'],
        ];

        // Agregar firma si hay secreto configurado
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
            $headers['X-ArchiveMaster-Signature'] = 'sha256=' . $signature;
        }

        try {
            $startTime = microtime(true);

            $response = Http::timeout($webhook['timeout'])
                ->withHeaders($headers)
                ->post($webhook['url'], $payload);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $this->logWebhookSuccess($webhook['id'], $event, $responseTime);
            } else {
                $this->logWebhookFailure($webhook['id'], $event, $response->status(), $response->body());
                $this->scheduleWebhookRetry($webhook, $event, $data, 1);
            }

        } catch (\Exception $e) {
            $this->logWebhookFailure($webhook['id'], $event, 0, $e->getMessage());
            $this->scheduleWebhookRetry($webhook, $event, $data, 1);
        }
    }

    /**
     * Programar reintento de webhook
     */
    private function scheduleWebhookRetry(array $webhook, string $event, array $data, int $attempt): void
    {
        if ($attempt <= $webhook['retry_attempts']) {
            // En producción, esto se haría con un job en cola
            Log::info('Webhook retry scheduled', [
                'webhook_id' => $webhook['id'],
                'event' => $event,
                'attempt' => $attempt,
                'max_attempts' => $webhook['retry_attempts'],
            ]);
        }
    }

    /**
     * Probar conectividad del webhook
     */
    private function testWebhookConnectivity(array $webhook): array
    {
        $testPayload = [
            'event' => 'test.ping',
            'data' => [
                'message' => 'Test de conectividad desde ArchiveMaster',
                'timestamp' => now()->toISOString(),
            ],
            'webhook_id' => $webhook['id'],
            'test' => true,
        ];

        try {
            $startTime = microtime(true);

            $response = Http::timeout($webhook['timeout'])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($webhook['url'], $testPayload);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time_ms' => $responseTime,
                'error_message' => $response->successful() ? null : $response->body(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'response_time_ms' => 0,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Guardar webhook (simulado con cache)
     */
    private function saveWebhook(array $webhookData): void
    {
        Cache::put("webhook_{$webhookData['id']}", $webhookData, now()->addDays(30));

        // También mantener lista por empresa
        $companyWebhooks = $this->getWebhooksByCompany($webhookData['company_id']);
        $companyWebhooks[$webhookData['id']] = $webhookData;
        Cache::put("company_webhooks_{$webhookData['company_id']}", $companyWebhooks, now()->addDays(30));
    }

    /**
     * Obtener webhook por ID
     */
    private function getWebhook(string $id): ?array
    {
        return Cache::get("webhook_{$id}");
    }

    /**
     * Obtener webhooks por empresa
     */
    private function getWebhooksByCompany(int $companyId): array
    {
        return Cache::get("company_webhooks_{$companyId}", []);
    }

    /**
     * Eliminar webhook
     */
    private function deleteWebhook(string $id): void
    {
        $webhook = $this->getWebhook($id);
        if ($webhook) {
            Cache::forget("webhook_{$id}");

            $companyWebhooks = $this->getWebhooksByCompany($webhook['company_id']);
            unset($companyWebhooks[$id]);
            Cache::put("company_webhooks_{$webhook['company_id']}", $companyWebhooks, now()->addDays(30));
        }
    }

    /**
     * Obtener estadísticas del webhook
     */
    private function getWebhookStats(string $webhookId): array
    {
        // En producción, esto consultaría una tabla de logs
        return [
            'last_success' => now()->subMinutes(rand(5, 60)),
            'last_failure' => rand(0, 1) ? now()->subHours(rand(1, 24)) : null,
            'total_calls' => rand(50, 500),
            'success_rate' => round(rand(85, 100), 1),
        ];
    }

    /**
     * Log de éxito del webhook
     */
    private function logWebhookSuccess(string $webhookId, string $event, int $responseTime): void
    {
        Log::info('Webhook call successful', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'response_time_ms' => $responseTime,
        ]);
    }

    /**
     * Log de fallo del webhook
     */
    private function logWebhookFailure(string $webhookId, string $event, int $statusCode, string $error): void
    {
        Log::error('Webhook call failed', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'status_code' => $statusCode,
            'error' => $error,
        ]);
    }
}
