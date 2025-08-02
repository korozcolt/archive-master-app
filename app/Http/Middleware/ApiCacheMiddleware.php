<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiCacheMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $ttl = 300): Response
    {
        // Solo cachear GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // No cachear si hay parámetros de autenticación específicos
        if ($request->hasHeader('Cache-Control') &&
            str_contains($request->header('Cache-Control'), 'no-cache')) {
            return $next($request);
        }

        // Generar clave de cache única
        $cacheKey = $this->generateCacheKey($request);

        // Intentar obtener respuesta del cache
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse) {
            // Agregar headers de cache
            return response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers'])
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', $cacheKey);
        }

        // Procesar request
        $response = $next($request);

        // Solo cachear respuestas exitosas
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($cacheKey, $response, $ttl);
            $response->header('X-Cache', 'MISS');
        }

        return $response->header('X-Cache-Key', $cacheKey);
    }

    /**
     * Generar clave de cache única para el request
     */
    private function generateCacheKey(Request $request): string
    {
        $user = $request->user();
        $userId = $user ? $user->id : 'guest';
        $companyId = $user ? $user->company_id : 0;

        // Incluir parámetros relevantes en la clave
        $params = $request->query();
        ksort($params); // Ordenar para consistencia

        $paramString = http_build_query($params);
        $path = $request->getPathInfo();

        return "api_cache:company_{$companyId}:user_{$userId}:" .
               md5($path . '?' . $paramString);
    }

    /**
     * Cachear la respuesta
     */
    private function cacheResponse(string $key, Response $response, int $ttl): void
    {
        $cacheData = [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $this->getCacheableHeaders($response),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($key, $cacheData, now()->addSeconds($ttl));
    }

    /**
     * Obtener headers que se pueden cachear
     */
    private function getCacheableHeaders(Response $response): array
    {
        $cacheableHeaders = [
            'Content-Type',
            'Content-Length',
            'X-Total-Count',
            'X-Per-Page',
            'X-Current-Page',
            'X-Last-Page',
        ];

        $headers = [];
        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}
