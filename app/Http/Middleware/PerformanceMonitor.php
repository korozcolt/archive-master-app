<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Procesar la request
        $response = $next($request);

        // Calcular métricas
        $executionTime = round((microtime(true) - $startTime) * 1000, 2); // en ms
        $memoryUsage = memory_get_usage(true) - $startMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Agregar headers de performance
        $response->headers->set('X-Response-Time', $executionTime . 'ms');
        $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsage));
        $response->headers->set('X-Peak-Memory', $this->formatBytes($peakMemory));

        // Log requests lentas (más de 2 segundos)
        if ($executionTime > 2000) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => $executionTime,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        }

        // Almacenar métricas en cache para dashboard
        $this->storeMetrics($request, $executionTime, $memoryUsage);

        return $response;
    }

    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Almacenar métricas para análisis
     */
    private function storeMetrics(Request $request, float $executionTime, int $memoryUsage): void
    {
        $key = 'performance_metrics:' . date('Y-m-d-H');

        $metrics = Cache::get($key, [
            'requests' => 0,
            'total_time' => 0,
            'total_memory' => 0,
            'slow_requests' => 0,
            'endpoints' => [],
        ]);

        $metrics['requests']++;
        $metrics['total_time'] += $executionTime;
        $metrics['total_memory'] += $memoryUsage;

        if ($executionTime > 2000) {
            $metrics['slow_requests']++;
        }

        // Métricas por endpoint
        $endpoint = $request->method() . ' ' . $request->path();
        if (!isset($metrics['endpoints'][$endpoint])) {
            $metrics['endpoints'][$endpoint] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
            ];
        }

        $metrics['endpoints'][$endpoint]['count']++;
        $metrics['endpoints'][$endpoint]['total_time'] += $executionTime;
        $metrics['endpoints'][$endpoint]['avg_time'] =
            $metrics['endpoints'][$endpoint]['total_time'] / $metrics['endpoints'][$endpoint]['count'];

        // Guardar por 24 horas
        Cache::put($key, $metrics, now()->addHours(24));
    }
}
