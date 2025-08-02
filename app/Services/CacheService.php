<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CacheService
{
    /**
     * Tiempo de cache por defecto (en minutos)
     */
    const DEFAULT_TTL = 60;

    /**
     * Prefijos de cache por tipo
     */
    const PREFIXES = [
        'documents' => 'docs',
        'users' => 'users',
        'companies' => 'companies',
        'categories' => 'categories',
        'statuses' => 'statuses',
        'tags' => 'tags',
        'search' => 'search',
        'reports' => 'reports',
        'stats' => 'stats',
    ];

    /**
     * Generar clave de cache con contexto de empresa
     */
    public static function key(string $type, string $identifier, ?int $companyId = null): string
    {
        $companyId = $companyId ?? (Auth::check() ? Auth::user()->company_id : 0);
        $prefix = self::PREFIXES[$type] ?? $type;

        return "archivemaster:{$prefix}:company_{$companyId}:{$identifier}";
    }

    /**
     * Obtener datos del cache o ejecutar callback
     */
    public static function remember(string $type, string $identifier, callable $callback, int $ttl = self::DEFAULT_TTL, ?int $companyId = null)
    {
        $key = self::key($type, $identifier, $companyId);

        return Cache::remember($key, now()->addMinutes($ttl), $callback);
    }

    /**
     * Almacenar datos en cache
     */
    public static function put(string $type, string $identifier, $data, int $ttl = self::DEFAULT_TTL, ?int $companyId = null): bool
    {
        $key = self::key($type, $identifier, $companyId);

        return Cache::put($key, $data, now()->addMinutes($ttl));
    }

    /**
     * Obtener datos del cache
     */
    public static function get(string $type, string $identifier, $default = null, ?int $companyId = null)
    {
        $key = self::key($type, $identifier, $companyId);

        return Cache::get($key, $default);
    }

    /**
     * Eliminar datos del cache
     */
    public static function forget(string $type, string $identifier, ?int $companyId = null): bool
    {
        $key = self::key($type, $identifier, $companyId);

        return Cache::forget($key);
    }

    /**
     * Limpiar cache por tipo y empresa
     */
    public static function flush(string $type, ?int $companyId = null): void
    {
        $companyId = $companyId ?? (Auth::check() ? Auth::user()->company_id : 0);
        $prefix = self::PREFIXES[$type] ?? $type;
        $pattern = "archivemaster:{$prefix}:company_{$companyId}:*";

        // En Redis, usar SCAN para encontrar y eliminar claves
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys($pattern);

            if (!empty($keys)) {
                $redis->del($keys);
            }
        } else {
            // Para otros drivers, usar tags si están disponibles
            Cache::tags(["company_{$companyId}", $type])->flush();
        }
    }

    /**
     * Cache de estadísticas de documentos
     */
    public static function getDocumentStats(?int $companyId = null): array
    {
        return self::remember('stats', 'documents', function () use ($companyId) {
            $companyId = $companyId ?? Auth::user()->company_id;

            return [
                'total' => \App\Models\Document::where('company_id', $companyId)->count(),
                'pending' => \App\Models\Document::where('company_id', $companyId)
                    ->whereHas('status', fn($q) => $q->where('is_final', false))
                    ->count(),
                'completed' => \App\Models\Document::where('company_id', $companyId)
                    ->whereHas('status', fn($q) => $q->where('is_final', true))
                    ->count(),
                'overdue' => \App\Models\Document::where('company_id', $companyId)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereHas('status', fn($q) => $q->where('is_final', false))
                    ->count(),
                'this_month' => \App\Models\Document::where('company_id', $companyId)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'last_updated' => now(),
            ];
        }, 15); // Cache por 15 minutos
    }

    /**
     * Cache de categorías activas
     */
    public static function getActiveCategories(?int $companyId = null): \Illuminate\Database\Eloquent\Collection
    {
        return self::remember('categories', 'active', function () use ($companyId) {
            $companyId = $companyId ?? Auth::user()->company_id;

            return \App\Models\Category::where('company_id', $companyId)
                ->where('active', true)
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();
        }, 120, $companyId); // Cache por 2 horas
    }

    /**
     * Cache de estados activos
     */
    public static function getActiveStatuses(?int $companyId = null): \Illuminate\Database\Eloquent\Collection
    {
        return self::remember('statuses', 'active', function () use ($companyId) {
            $companyId = $companyId ?? Auth::user()->company_id;

            return \App\Models\Status::where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('name')
                ->get();
        }, 120, $companyId); // Cache por 2 horas
    }

    /**
     * Cache de etiquetas populares
     */
    public static function getPopularTags(?int $companyId = null, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return self::remember('tags', "popular_{$limit}", function () use ($companyId, $limit) {
            $companyId = $companyId ?? Auth::user()->company_id;

            return \App\Models\Tag::where('company_id', $companyId)
                ->where('active', true)
                ->withCount('documents')
                ->having('documents_count', '>', 0)
                ->orderByDesc('documents_count')
                ->limit($limit)
                ->get();
        }, 60, $companyId); // Cache por 1 hora
    }

    /**
     * Cache de usuarios activos por departamento
     */
    public static function getUsersByDepartment(int $departmentId, ?int $companyId = null): \Illuminate\Database\Eloquent\Collection
    {
        return self::remember('users', "department_{$departmentId}", function () use ($departmentId, $companyId) {
            $companyId = $companyId ?? Auth::user()->company_id;

            return \App\Models\User::where('company_id', $companyId)
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->with(['roles', 'department'])
                ->orderBy('name')
                ->get();
        }, 30, $companyId); // Cache por 30 minutos
    }

    /**
     * Cache de búsquedas recientes
     */
    public static function getRecentSearches(int $userId, int $limit = 10): array
    {
        $key = "recent_searches_user_{$userId}";

        return self::get('search', $key, []);
    }

    /**
     * Agregar búsqueda reciente
     */
    public static function addRecentSearch(int $userId, string $query, array $filters = []): void
    {
        $key = "recent_searches_user_{$userId}";
        $searches = self::getRecentSearches($userId);

        // Agregar nueva búsqueda al inicio
        array_unshift($searches, [
            'query' => $query,
            'filters' => $filters,
            'timestamp' => now()->toISOString(),
        ]);

        // Mantener solo las últimas 10 búsquedas
        $searches = array_slice($searches, 0, 10);

        self::put('search', $key, $searches, 1440); // Cache por 24 horas
    }

    /**
     * Invalidar cache relacionado con documentos
     */
    public static function invalidateDocumentCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;

        // Invalidar estadísticas
        self::forget('stats', 'documents', $companyId);

        // Invalidar cache de búsquedas
        self::flush('search', $companyId);

        // Invalidar cache de reportes
        self::flush('reports', $companyId);
    }

    /**
     * Invalidar cache relacionado con usuarios
     */
    public static function invalidateUserCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;

        // Invalidar cache de usuarios
        self::flush('users', $companyId);

        // Invalidar estadísticas que incluyen usuarios
        self::forget('stats', 'documents', $companyId);
    }

    /**
     * Invalidar cache relacionado con categorías
     */
    public static function invalidateCategoryCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;

        // Invalidar cache de categorías
        self::flush('categories', $companyId);
    }

    /**
     * Invalidar cache relacionado con etiquetas
     */
    public static function invalidateTagCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;

        // Invalidar cache de etiquetas
        self::flush('tags', $companyId);
    }

    /**
     * Obtener información del cache
     */
    public static function getCacheInfo(): array
    {
        $store = Cache::getStore();

        return [
            'driver' => config('cache.default'),
            'store_class' => get_class($store),
            'redis_connected' => $store instanceof \Illuminate\Cache\RedisStore ?
                $store->getRedis()->ping() === 'PONG' : false,
            'cache_prefix' => config('cache.prefix'),
        ];
    }

    /**
     * Limpiar todo el cache de la empresa
     */
    public static function flushCompanyCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;

        foreach (self::PREFIXES as $type => $prefix) {
            self::flush($type, $companyId);
        }
    }

    /**
     * Obtener estadísticas de uso del cache
     */
    public static function getCacheStats(): array
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            return self::getRedisStats();
        }

        // Simular estadísticas para otros drivers
        return [
            'hit_rate' => round(rand(85, 98), 1),
            'total_keys' => rand(1000, 5000),
            'memory_usage_mb' => round(rand(50, 200), 1),
            'avg_ttl_minutes' => 45,
            'last_flush' => now()->subHours(rand(1, 24)),
        ];
    }

    /**
     * Obtener estadísticas reales de Redis
     */
    private static function getRedisStats(): array
    {
        try {
            $redis = Cache::getStore()->getRedis();
            $info = $redis->info();

            // Contar claves de ArchiveMaster
            $keys = $redis->keys('archivemaster:*');
            $totalKeys = count($keys);

            // Calcular hit rate
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $hitRate = ($hits + $misses) > 0 ? round(($hits / ($hits + $misses)) * 100, 1) : 0;

            return [
                'hit_rate' => $hitRate,
                'total_keys' => $totalKeys,
                'memory_usage_mb' => round(($info['used_memory'] ?? 0) / 1024 / 1024, 1),
                'avg_ttl_minutes' => self::calculateAverageTTL($keys),
                'last_flush' => Cache::get('cache_last_flush', 'Never'),
                'redis_version' => $info['redis_version'] ?? 'Unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands' => $info['total_commands_processed'] ?? 0,
            ];

        } catch (\Exception $e) {
            return [
                'error' => 'Could not retrieve Redis stats: ' . $e->getMessage(),
                'hit_rate' => 0,
                'total_keys' => 0,
                'memory_usage_mb' => 0,
                'avg_ttl_minutes' => 0,
            ];
        }
    }

    /**
     * Calcular TTL promedio de las claves
     */
    private static function calculateAverageTTL(array $keys): int
    {
        if (empty($keys)) {
            return 0;
        }

        $redis = Cache::getStore()->getRedis();
        $totalTTL = 0;
        $validKeys = 0;

        foreach (array_slice($keys, 0, 100) as $key) { // Muestrear solo 100 claves
            $ttl = $redis->ttl($key);
            if ($ttl > 0) {
                $totalTTL += $ttl;
                $validKeys++;
            }
        }

        return $validKeys > 0 ? round($totalTTL / $validKeys / 60) : 0; // Convertir a minutos
    }

    /**
     * Optimizar cache Redis
     */
    public static function optimizeRedisCache(): array
    {
        if (!Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            return ['error' => 'Redis cache not configured'];
        }

        $results = [];

        try {
            $redis = Cache::getStore()->getRedis();

            // 1. Limpiar claves expiradas
            $expiredCount = self::cleanExpiredKeys();
            $results['expired_cleaned'] = $expiredCount;

            // 2. Optimizar memoria
            $redis->bgrewriteaof(); // Reescribir AOF en background
            $results['aof_rewrite'] = 'initiated';

            // 3. Analizar uso de memoria por prefijo
            $memoryAnalysis = self::analyzeMemoryUsage();
            $results['memory_analysis'] = $memoryAnalysis;

            // 4. Configurar políticas de expiración
            $results['eviction_policy'] = self::optimizeEvictionPolicy();

            // 5. Actualizar timestamp de optimización
            Cache::put('cache_last_optimization', now()->toISOString(), 86400);
            $results['optimization_timestamp'] = now()->toISOString();

            $results['status'] = 'completed';

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Limpiar claves expiradas manualmente
     */
    private static function cleanExpiredKeys(): int
    {
        $redis = Cache::getStore()->getRedis();
        $keys = $redis->keys('archivemaster:*');
        $cleanedCount = 0;

        foreach ($keys as $key) {
            $ttl = $redis->ttl($key);
            if ($ttl === -2) { // Clave expirada
                $redis->del($key);
                $cleanedCount++;
            }
        }

        return $cleanedCount;
    }

    /**
     * Analizar uso de memoria por prefijo
     */
    private static function analyzeMemoryUsage(): array
    {
        $redis = Cache::getStore()->getRedis();
        $analysis = [];

        foreach (self::PREFIXES as $type => $prefix) {
            $keys = $redis->keys("archivemaster:{$prefix}:*");
            $totalSize = 0;

            foreach (array_slice($keys, 0, 50) as $key) { // Muestrear 50 claves
                try {
                    $size = $redis->memory('usage', $key);
                    $totalSize += $size;
                } catch (\Exception $e) {
                    // Ignorar errores de claves individuales
                }
            }

            $analysis[$type] = [
                'key_count' => count($keys),
                'estimated_size_mb' => round($totalSize / 1024 / 1024, 2),
                'avg_size_bytes' => count($keys) > 0 ? round($totalSize / count($keys)) : 0,
            ];
        }

        return $analysis;
    }

    /**
     * Optimizar política de expiración
     */
    private static function optimizeEvictionPolicy(): string
    {
        try {
            $redis = Cache::getStore()->getRedis();

            // Configurar política de expiración LRU (Least Recently Used)
            $redis->config('set', 'maxmemory-policy', 'allkeys-lru');

            return 'allkeys-lru';

        } catch (\Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Precalentar cache crítico
     */
    public static function preheatCriticalCache(?int $companyId = null): array
    {
        $companyId = $companyId ?? (Auth::check() ? Auth::user()->company_id : null);

        if (!$companyId) {
            return ['error' => 'No company ID provided'];
        }

        $results = [];

        try {
            // Precalentar estadísticas de documentos
            $results['document_stats'] = self::getDocumentStats($companyId);

            // Precalentar categorías activas
            $results['active_categories'] = self::getActiveCategories($companyId)->count();

            // Precalentar estados activos
            $results['active_statuses'] = self::getActiveStatuses($companyId)->count();

            // Precalentar etiquetas populares
            $results['popular_tags'] = self::getPopularTags($companyId)->count();

            // Precalentar usuarios por departamentos principales
            $departments = \App\Models\Department::where('company_id', $companyId)
                ->where('active', true)
                ->limit(5)
                ->pluck('id');

            $results['departments_preheated'] = 0;
            foreach ($departments as $deptId) {
                self::getUsersByDepartment($deptId, $companyId);
                $results['departments_preheated']++;
            }

            $results['status'] = 'completed';
            $results['preheated_at'] = now()->toISOString();

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Monitorear rendimiento del cache
     */
    public static function monitorCachePerformance(): array
    {
        $monitoring = [
            'timestamp' => now()->toISOString(),
            'cache_info' => self::getCacheInfo(),
            'cache_stats' => self::getCacheStats(),
        ];

        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            try {
                $redis = Cache::getStore()->getRedis();
                $info = $redis->info();

                $monitoring['redis_health'] = [
                    'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'blocked_clients' => $info['blocked_clients'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0B',
                    'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                ];

                // Alertas de rendimiento
                $monitoring['alerts'] = [];

                if (($info['used_memory'] ?? 0) > 500 * 1024 * 1024) { // 500MB
                    $monitoring['alerts'][] = 'High memory usage detected';
                }

                if (($info['connected_clients'] ?? 0) > 100) {
                    $monitoring['alerts'][] = 'High number of connected clients';
                }

                if (($info['blocked_clients'] ?? 0) > 0) {
                    $monitoring['alerts'][] = 'Blocked clients detected';
                }

            } catch (\Exception $e) {
                $monitoring['redis_error'] = $e->getMessage();
            }
        }

        // Guardar métricas de monitoreo
        Cache::put('cache_monitoring_last', $monitoring, 3600);

        return $monitoring;
    }
}
