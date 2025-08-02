<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = 'api'): Response
    {
        $identifier = $this->getIdentifier($request);

        // Diferentes límites según el tipo de endpoint
        $limits = $this->getLimitsForKey($key);

        foreach ($limits as $limit) {
            $rateLimiterKey = $key . ':' . $identifier . ':' . $limit['window'];

            if (RateLimiter::tooManyAttempts($rateLimiterKey, $limit['attempts'])) {
                return $this->buildResponse($rateLimiterKey, $limit['attempts']);
            }

            RateLimiter::hit($rateLimiterKey, $limit['decay']);
        }

        $response = $next($request);

        // Agregar headers de rate limiting
        return $this->addRateLimitHeaders($response, $key . ':' . $identifier, $limits[0]);
    }

    /**
     * Get the rate limiting identifier for the request.
     */
    protected function getIdentifier(Request $request): string
    {
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        return 'ip:' . $request->ip();
    }

    /**
     * Get rate limits for the given key.
     */
    protected function getLimitsForKey(string $key): array
    {
        return match ($key) {
            'auth' => [
                ['attempts' => 5, 'decay' => 60, 'window' => '1min'],     // 5 intentos por minuto
                ['attempts' => 20, 'decay' => 3600, 'window' => '1hour'], // 20 intentos por hora
            ],
            'search' => [
                ['attempts' => 100, 'decay' => 60, 'window' => '1min'],    // 100 búsquedas por minuto
                ['attempts' => 1000, 'decay' => 3600, 'window' => '1hour'], // 1000 búsquedas por hora
            ],
            'hardware' => [
                ['attempts' => 200, 'decay' => 60, 'window' => '1min'],    // 200 escaneos por minuto
                ['attempts' => 5000, 'decay' => 3600, 'window' => '1hour'], // 5000 escaneos por hora
            ],
            'webhooks' => [
                ['attempts' => 10, 'decay' => 60, 'window' => '1min'],     // 10 operaciones por minuto
                ['attempts' => 100, 'decay' => 3600, 'window' => '1hour'], // 100 operaciones por hora
            ],
            default => [
                ['attempts' => 60, 'decay' => 60, 'window' => '1min'],     // 60 requests por minuto
                ['attempts' => 1000, 'decay' => 3600, 'window' => '1hour'], // 1000 requests por hora
            ],
        };
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Demasiadas solicitudes. Intenta de nuevo en ' . $retryAfter . ' segundos.',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $retryAfter,
            'max_attempts' => $maxAttempts,
            'timestamp' => now()->toISOString(),
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addRateLimitHeaders(Response $response, string $key, array $limit): Response
    {
        $remaining = RateLimiter::remaining($key, $limit['attempts']);
        $retryAfter = RateLimiter::availableIn($key);

        $response->headers->add([
            'X-RateLimit-Limit' => $limit['attempts'],
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }
}
