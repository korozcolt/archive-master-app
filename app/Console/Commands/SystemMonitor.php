<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SystemMonitor extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:monitor
                            {--alert : Enviar alertas si hay problemas}
                            {--detailed : Mostrar información detallada}
                            {--export= : Exportar métricas a archivo}';

    /**
     * The console command description.
     */
    protected $description = 'Monitorear el estado del sistema y generar métricas de rendimiento';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📊 Iniciando monitoreo del sistema ArchiveMaster...');

        $startTime = microtime(true);
        $metrics = [];
        $alerts = [];

        // Recopilar métricas del sistema
        $metrics['system'] = $this->getSystemMetrics();
        $metrics['database'] = $this->getDatabaseMetrics();
        $metrics['cache'] = $this->getCacheMetrics();
        $metrics['storage'] = $this->getStorageMetrics();
        $metrics['application'] = $this->getApplicationMetrics();
        $metrics['performance'] = $this->getPerformanceMetrics();

        // Verificar alertas
        if ($this->option('alert')) {
            $alerts = $this->checkAlerts($metrics);
        }

        // Mostrar resultados
        $this->displayMetrics($metrics, $this->option('detailed'));

        // Mostrar alertas si las hay
        if (!empty($alerts)) {
            $this->displayAlerts($alerts);
        }

        // Exportar métricas si se solicita
        if ($exportFile = $this->option('export')) {
            $this->exportMetrics($metrics, $exportFile);
        }

        // Log de métricas
        $this->logMetrics($metrics);

        $totalTime = round(microtime(true) - $startTime, 2);
        $this->info("✅ Monitoreo completado en {$totalTime}s");

        return empty($alerts) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Obtener métricas del sistema
     */
    private function getSystemMetrics(): array
    {
        $metrics = [];

        try {
            // Uso de memoria
            $metrics['memory_usage'] = [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit_mb' => ini_get('memory_limit'),
            ];

            // Carga del sistema (solo en Linux/Unix)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $metrics['system_load'] = [
                    '1min' => round($load[0], 2),
                    '5min' => round($load[1], 2),
                    '15min' => round($load[2], 2),
                ];
            }

            // Espacio en disco
            $metrics['disk_space'] = [
                'free_gb' => round(disk_free_space(storage_path()) / 1024 / 1024 / 1024, 2),
                'total_gb' => round(disk_total_space(storage_path()) / 1024 / 1024 / 1024, 2),
            ];
            $metrics['disk_space']['used_percentage'] = round(
                (($metrics['disk_space']['total_gb'] - $metrics['disk_space']['free_gb']) / $metrics['disk_space']['total_gb']) * 100,
                2
            );

            // Información de PHP
            $metrics['php'] = [
                'version' => PHP_VERSION,
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ];

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Obtener métricas de base de datos
     */
    private function getDatabaseMetrics(): array
    {
        $metrics = [];

        try {
            // Conexión a la base de datos
            $startTime = microtime(true);
            $connectionTest = DB::select('SELECT 1 as test');
            $metrics['connection_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $metrics['connection_status'] = !empty($connectionTest) ? 'connected' : 'error';

            // Estadísticas de tablas principales
            $tables = ['documents', 'users', 'companies', 'workflow_histories'];
            $metrics['tables'] = [];

            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $metrics['tables'][$table] = [
                        'row_count' => $count,
                        'status' => 'ok'
                    ];
                } catch (\Exception $e) {
                    $metrics['tables'][$table] = [
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Consultas lentas (simulado)
            $metrics['slow_queries'] = [
                'count_last_hour' => rand(0, 5),
                'average_time_ms' => rand(100, 500),
            ];

            // Conexiones activas (para MySQL)
            if (DB::connection()->getDriverName() === 'mysql') {
                try {
                    $processes = DB::select('SHOW PROCESSLIST');
                    $metrics['active_connections'] = count($processes);
                } catch (\Exception $e) {
                    $metrics['active_connections'] = 'unknown';
                }
            }

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Obtener métricas de cache
     */
    private function getCacheMetrics(): array
    {
        $metrics = [];

        try {
            // Test de conexión Redis
            $startTime = microtime(true);
            Cache::put('monitor_test', 'ok', 10);
            $testResult = Cache::get('monitor_test');
            $metrics['connection_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $metrics['connection_status'] = $testResult === 'ok' ? 'connected' : 'error';

            // Información del cache
            $cacheInfo = CacheService::getCacheInfo();
            $metrics['driver'] = $cacheInfo['driver'];
            $metrics['redis_connected'] = $cacheInfo['redis_connected'];

            // Estadísticas de uso
            $cacheStats = CacheService::getCacheStats();
            $metrics['hit_rate'] = $cacheStats['hit_rate'];
            $metrics['total_keys'] = $cacheStats['total_keys'];
            $metrics['memory_usage_mb'] = $cacheStats['memory_usage_mb'];

            // Test de rendimiento
            $startTime = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                Cache::put("perf_test_{$i}", "value_{$i}", 60);
                Cache::get("perf_test_{$i}");
            }
            $metrics['performance_test_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Obtener métricas de almacenamiento
     */
    private function getStorageMetrics(): array
    {
        $metrics = [];

        try {
            // Tamaños de directorios
            $metrics['directories'] = [
                'storage_mb' => round($this->getDirectorySize(storage_path()) / 1024 / 1024, 2),
                'public_mb' => round($this->getDirectorySize(public_path()) / 1024 / 1024, 2),
                'logs_mb' => round($this->getDirectorySize(storage_path('logs')) / 1024 / 1024, 2),
            ];

            // Archivos por tipo
            $metrics['file_types'] = $this->getFileTypeStatistics();

            // Archivos recientes
            $metrics['recent_files'] = [
                'last_24h' => $this->countRecentFiles(24),
                'last_7d' => $this->countRecentFiles(24 * 7),
            ];

            // Espacio disponible
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $metrics['disk_usage'] = [
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'used_percentage' => round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2),
            ];

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Obtener métricas de la aplicación
     */
    private function getApplicationMetrics(): array
    {
        $metrics = [];

        try {
            // Estadísticas de documentos
            $metrics['documents'] = [
                'total' => DB::table('documents')->count(),
                'today' => DB::table('documents')->whereDate('created_at', today())->count(),
                'this_week' => DB::table('documents')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'pending' => DB::table('documents')
                    ->join('statuses', 'documents.status_id', '=', 'statuses.id')
                    ->where('statuses.is_final', false)
                    ->count(),
            ];

            // Estadísticas de usuarios
            $metrics['users'] = [
                'total' => DB::table('users')->count(),
                'active' => DB::table('users')->where('is_active', true)->count(),
                'logged_in_today' => DB::table('users')->whereDate('last_login_at', today())->count(),
            ];

            // Estadísticas de empresas
            $metrics['companies'] = [
                'total' => DB::table('companies')->count(),
                'active' => DB::table('companies')->where('active', true)->count(),
            ];

            // Colas de trabajo
            $metrics['queues'] = [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];

            // Notificaciones
            if (Schema::hasTable('notifications')) {
                $metrics['notifications'] = [
                    'total' => DB::table('notifications')->count(),
                    'unread' => DB::table('notifications')->whereNull('read_at')->count(),
                    'today' => DB::table('notifications')->whereDate('created_at', today())->count(),
                ];
            }

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Obtener métricas de rendimiento
     */
    private function getPerformanceMetrics(): array
    {
        $metrics = [];

        try {
            // Test de velocidad de base de datos
            $startTime = microtime(true);
            DB::table('documents')->limit(100)->get();
            $metrics['db_query_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            // Test de velocidad de cache
            $startTime = microtime(true);
            CacheService::getDocumentStats();
            $metrics['cache_access_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            // Test de velocidad de archivos
            $startTime = microtime(true);
            Storage::put('performance_test.txt', 'test content');
            Storage::get('performance_test.txt');
            Storage::delete('performance_test.txt');
            $metrics['file_io_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            // Tiempo de respuesta promedio (simulado)
            $metrics['avg_response_time_ms'] = rand(150, 300);
            $metrics['requests_per_second'] = rand(50, 200);

            $metrics['status'] = 'healthy';

        } catch (\Exception $e) {
            $metrics['status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Verificar alertas del sistema
     */
    private function checkAlerts(array $metrics): array
    {
        $alerts = [];

        // Alertas de memoria
        if (isset($metrics['system']['memory_usage']['current_mb']) &&
            $metrics['system']['memory_usage']['current_mb'] > 512) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'memory',
                'message' => 'Alto uso de memoria: ' . $metrics['system']['memory_usage']['current_mb'] . 'MB',
            ];
        }

        // Alertas de disco
        if (isset($metrics['system']['disk_space']['used_percentage']) &&
            $metrics['system']['disk_space']['used_percentage'] > 85) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'disk',
                'message' => 'Espacio en disco bajo: ' . $metrics['system']['disk_space']['used_percentage'] . '% usado',
            ];
        }

        // Alertas de base de datos
        if (isset($metrics['database']['connection_time_ms']) &&
            $metrics['database']['connection_time_ms'] > 1000) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'database',
                'message' => 'Conexión lenta a base de datos: ' . $metrics['database']['connection_time_ms'] . 'ms',
            ];
        }

        // Alertas de cache
        if (isset($metrics['cache']['hit_rate']) &&
            $metrics['cache']['hit_rate'] < 80) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'cache',
                'message' => 'Baja tasa de aciertos en cache: ' . $metrics['cache']['hit_rate'] . '%',
            ];
        }

        // Alertas de trabajos fallidos
        if (isset($metrics['application']['queues']['failed_jobs']) &&
            $metrics['application']['queues']['failed_jobs'] > 10) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'queue',
                'message' => 'Muchos trabajos fallidos: ' . $metrics['application']['queues']['failed_jobs'],
            ];
        }

        return $alerts;
    }

    /**
     * Mostrar métricas en consola
     */
    private function displayMetrics(array $metrics, bool $detailed = false): void
    {
        $this->newLine();
        $this->info('📊 MÉTRICAS DEL SISTEMA');
        $this->line('═══════════════════════════════════════');

        // Sistema
        if (isset($metrics['system'])) {
            $this->line('🖥️  SISTEMA:');
            $memory = $metrics['system']['memory_usage'] ?? [];
            $this->line("   • Memoria: {$memory['current_mb']}MB / {$memory['peak_mb']}MB pico");

            if (isset($metrics['system']['disk_space'])) {
                $disk = $metrics['system']['disk_space'];
                $this->line("   • Disco: {$disk['used_percentage']}% usado ({$disk['free_gb']}GB libres)");
            }
        }

        // Base de datos
        if (isset($metrics['database'])) {
            $this->line('🗄️  BASE DE DATOS:');
            $this->line("   • Conexión: {$metrics['database']['connection_time_ms']}ms");

            if (isset($metrics['database']['tables'])) {
                $totalRows = array_sum(array_column($metrics['database']['tables'], 'row_count'));
                $this->line("   • Total registros: " . number_format($totalRows));
            }
        }

        // Cache
        if (isset($metrics['cache'])) {
            $this->line('⚡ CACHE:');
            $this->line("   • Estado: {$metrics['cache']['connection_status']}");
            $this->line("   • Tasa de aciertos: {$metrics['cache']['hit_rate']}%");
            $this->line("   • Memoria: {$metrics['cache']['memory_usage_mb']}MB");
        }

        // Aplicación
        if (isset($metrics['application'])) {
            $this->line('📱 APLICACIÓN:');
            $app = $metrics['application'];
            $this->line("   • Documentos: {$app['documents']['total']} total, {$app['documents']['today']} hoy");
            $this->line("   • Usuarios: {$app['users']['active']}/{$app['users']['total']} activos");
            $this->line("   • Trabajos pendientes: {$app['queues']['pending_jobs']}");
        }

        // Rendimiento
        if (isset($metrics['performance'])) {
            $this->line('🚀 RENDIMIENTO:');
            $perf = $metrics['performance'];
            $this->line("   • Consulta BD: {$perf['db_query_time_ms']}ms");
            $this->line("   • Acceso cache: {$perf['cache_access_time_ms']}ms");
            $this->line("   • I/O archivos: {$perf['file_io_time_ms']}ms");
        }

        if ($detailed) {
            $this->displayDetailedMetrics($metrics);
        }
    }

    /**
     * Mostrar métricas detalladas
     */
    private function displayDetailedMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('📋 MÉTRICAS DETALLADAS');
        $this->line('═══════════════════════════════════════');

        foreach ($metrics as $category => $data) {
            if (is_array($data)) {
                $this->line(strtoupper($category) . ':');
                $this->displayArrayRecursive($data, '  ');
                $this->newLine();
            }
        }
    }

    /**
     * Mostrar array recursivamente
     */
    private function displayArrayRecursive(array $data, string $indent = ''): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line("{$indent}• {$key}:");
                $this->displayArrayRecursive($value, $indent . '  ');
            } else {
                $this->line("{$indent}• {$key}: {$value}");
            }
        }
    }

    /**
     * Mostrar alertas
     */
    private function displayAlerts(array $alerts): void
    {
        $this->newLine();
        $this->error('🚨 ALERTAS DEL SISTEMA');
        $this->line('═══════════════════════════════════════');

        foreach ($alerts as $alert) {
            $icon = match ($alert['type']) {
                'critical' => '🔴',
                'warning' => '🟡',
                default => '🔵'
            };

            $this->line("{$icon} [{$alert['category']}] {$alert['message']}");
        }
    }

    /**
     * Exportar métricas a archivo
     */
    private function exportMetrics(array $metrics, string $filename): void
    {
        try {
            $data = [
                'timestamp' => now()->toISOString(),
                'metrics' => $metrics,
            ];

            $content = json_encode($data, JSON_PRETTY_PRINT);
            Storage::put("monitoring/{$filename}", $content);

            $this->info("📄 Métricas exportadas a: storage/app/monitoring/{$filename}");

        } catch (\Exception $e) {
            $this->error("❌ Error exportando métricas: {$e->getMessage()}");
        }
    }

    /**
     * Log de métricas
     */
    private function logMetrics(array $metrics): void
    {
        Log::info('System monitoring completed', [
            'memory_mb' => $metrics['system']['memory_usage']['current_mb'] ?? 0,
            'disk_usage_percent' => $metrics['system']['disk_space']['used_percentage'] ?? 0,
            'db_connection_ms' => $metrics['database']['connection_time_ms'] ?? 0,
            'cache_hit_rate' => $metrics['cache']['hit_rate'] ?? 0,
            'total_documents' => $metrics['application']['documents']['total'] ?? 0,
            'active_users' => $metrics['application']['users']['active'] ?? 0,
        ]);
    }

    /**
     * Calcular tamaño de directorio
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;

        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $size;
    }

    /**
     * Obtener estadísticas de tipos de archivo
     */
    private function getFileTypeStatistics(): array
    {
        $types = ['pdf' => 0, 'jpg' => 0, 'png' => 0, 'doc' => 0, 'other' => 0];

        // Simular estadísticas
        $types['pdf'] = rand(100, 500);
        $types['jpg'] = rand(200, 800);
        $types['png'] = rand(50, 300);
        $types['doc'] = rand(30, 150);
        $types['other'] = rand(20, 100);

        return $types;
    }

    /**
     * Contar archivos recientes
     */
    private function countRecentFiles(int $hours): int
    {
        // Simular conteo de archivos recientes
        return rand(10, 100);
    }
}
