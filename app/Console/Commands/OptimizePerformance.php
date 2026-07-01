<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use App\Services\CDNService;
use App\Services\FileCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OptimizePerformance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:optimize-performance
                            {--cache : Optimizar cache}
                            {--database : Optimizar base de datos}
                            {--files : Comprimir archivos}
                            {--cdn : Optimizar CDN}
                            {--all : Ejecutar todas las optimizaciones}
                            {--force : Forzar optimizaciones sin confirmación}';

    /**
     * The console command description.
     */
    protected $description = 'Optimizar el rendimiento del sistema (cache, base de datos, archivos) v1.0.1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Iniciando optimización de rendimiento del sistema...');

        $startTime = microtime(true);
        $optimizations = [];

        // Determinar qué optimizaciones ejecutar
        $runCache = $this->option('cache') || $this->option('all');
        $runDatabase = $this->option('database') || $this->option('all');
        $runFiles = $this->option('files') || $this->option('all');
        $runCDN = $this->option('cdn') || $this->option('all');
        $force = $this->option('force');

        if (! $runCache && ! $runDatabase && ! $runFiles && ! $runCDN) {
            $this->error('❌ Debe especificar al menos una optimización: --cache, --database, --files, --cdn, o --all');

            return self::FAILURE;
        }

        // Mostrar resumen de optimizaciones
        $this->info('📋 Optimizaciones a ejecutar:');
        if ($runCache) {
            $this->line('  • Cache y Redis');
        }
        if ($runDatabase) {
            $this->line('  • Base de datos e índices');
        }
        if ($runFiles) {
            $this->line('  • Compresión de archivos');
        }
        if ($runCDN) {
            $this->line('  • CDN y assets estáticos');
        }

        // Confirmar si no es forzado
        if (! $force && ! $this->confirm('¿Continuar con las optimizaciones?')) {
            $this->info('❌ Optimización cancelada por el usuario.');

            return self::SUCCESS;
        }

        $this->newLine();

        // Ejecutar optimizaciones
        if ($runCache) {
            $optimizations['cache'] = $this->optimizeCache();
        }

        if ($runDatabase) {
            $optimizations['database'] = $this->optimizeDatabase();
        }

        if ($runFiles) {
            $optimizations['files'] = $this->optimizeFiles();
        }

        if ($runCDN) {
            $optimizations['cdn'] = $this->optimizeCDN();
        }

        // Mostrar resumen final
        $totalTime = round(microtime(true) - $startTime, 2);
        $this->showOptimizationSummary($optimizations, $totalTime);

        return self::SUCCESS;
    }

    /**
     * Optimizar cache y Redis
     */
    private function optimizeCache(): array
    {
        $this->info('🔄 Optimizando cache y Redis...');

        $startTime = microtime(true);
        $results = [];

        try {
            // Limpiar cache de configuración
            $this->line('  • Limpiando cache de configuración...');
            Artisan::call('config:cache');
            $results['config_cache'] = 'OK';

            // Limpiar cache de rutas
            $this->line('  • Limpiando cache de rutas...');
            Artisan::call('route:cache');
            $results['route_cache'] = 'OK';

            // Limpiar cache de vistas
            $this->line('  • Limpiando cache de vistas...');
            Artisan::call('view:cache');
            $results['view_cache'] = 'OK';

            // Optimizar autoloader
            $this->line('  • Optimizando autoloader...');
            Artisan::call('optimize');
            $results['autoloader'] = 'OK';

            // Precalentar cache de datos críticos
            $this->line('  • Precalentando cache de datos críticos...');
            $preheatResult = CacheService::preheatCriticalCache();
            $results['preheat_cache'] = $preheatResult['status'] === 'completed' ? 'OK' : 'ERROR';

            // Optimizar Redis
            $this->line('  • Optimizando Redis...');
            $redisResult = CacheService::optimizeRedisCache();
            $results['redis_optimization'] = $redisResult['status'] === 'completed' ? 'OK' : 'ERROR';

            // Limpiar cache antiguo
            $this->line('  • Limpiando cache antiguo...');
            $this->cleanOldCache();
            $results['clean_old_cache'] = 'OK';

            // Verificar conexión Redis
            $this->line('  • Verificando conexión Redis...');
            $redisStatus = $this->checkRedisConnection();
            $results['redis_connection'] = $redisStatus ? 'OK' : 'ERROR';

            $results['execution_time'] = round(microtime(true) - $startTime, 2);
            $results['status'] = 'completed';

            $this->info('✅ Optimización de cache completada');

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            $this->error('❌ Error en optimización de cache: '.$e->getMessage());
            Log::error('Cache optimization failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Optimizar base de datos
     */
    private function optimizeDatabase(): array
    {
        $this->info('🔄 Optimizando base de datos...');

        $startTime = microtime(true);
        $results = [];

        try {
            // Ejecutar migraciones pendientes
            $this->line('  • Verificando migraciones pendientes...');
            Artisan::call('migrate', ['--force' => true]);
            $results['migrations'] = 'OK';

            // Analizar tablas para optimizar índices
            $this->line('  • Analizando tablas principales...');
            $tableStats = $this->analyzeTablePerformance();
            $results['table_analysis'] = $tableStats;

            // Optimizar tablas MySQL (si es MySQL)
            if (DB::connection()->getDriverName() === 'mysql') {
                $this->line('  • Optimizando tablas MySQL...');
                $this->optimizeMySQLTables();
                $results['mysql_optimization'] = 'OK';
            }

            // Limpiar logs antiguos
            $this->line('  • Limpiando logs antiguos...');
            $this->cleanOldLogs();
            $results['clean_logs'] = 'OK';

            // Verificar integridad de índices
            $this->line('  • Verificando integridad de índices...');
            $indexStats = $this->checkIndexIntegrity();
            $results['index_integrity'] = $indexStats;

            $results['execution_time'] = round(microtime(true) - $startTime, 2);
            $results['status'] = 'completed';

            $this->info('✅ Optimización de base de datos completada');

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            $this->error('❌ Error en optimización de base de datos: '.$e->getMessage());
            Log::error('Database optimization failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Optimizar archivos
     */
    private function optimizeFiles(): array
    {
        $this->info('🔄 Optimizando archivos...');

        $startTime = microtime(true);
        $results = [];

        try {
            $compressionService = new FileCompressionService;

            // Comprimir archivos en directorio de documentos
            $this->line('  • Comprimiendo archivos de documentos...');
            $documentsResult = $compressionService->compressExistingFiles('documents', 50);
            $results['documents_compression'] = $documentsResult;

            // Comprimir archivos en directorio público
            $this->line('  • Comprimiendo archivos públicos...');
            $publicResult = $compressionService->compressExistingFiles('public', 30);
            $results['public_compression'] = $publicResult;

            // Limpiar archivos temporales
            $this->line('  • Limpiando archivos temporales...');
            $this->cleanTemporaryFiles();
            $results['clean_temp_files'] = 'OK';

            // Optimizar assets
            $this->line('  • Optimizando assets...');
            if (file_exists(base_path('package.json'))) {
                exec('npm run build 2>&1', $output, $returnCode);
                $results['assets_build'] = $returnCode === 0 ? 'OK' : 'ERROR';
            } else {
                $results['assets_build'] = 'SKIPPED';
            }

            // Generar estadísticas de almacenamiento
            $this->line('  • Generando estadísticas de almacenamiento...');
            $storageStats = $this->getStorageStatistics();
            $results['storage_stats'] = $storageStats;

            $results['execution_time'] = round(microtime(true) - $startTime, 2);
            $results['status'] = 'completed';

            $this->info('✅ Optimización de archivos completada');

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            $this->error('❌ Error en optimización de archivos: '.$e->getMessage());
            Log::error('File optimization failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Optimizar CDN y assets estáticos
     */
    private function optimizeCDN(): array
    {
        $this->info('🔄 Optimizando CDN y assets estáticos...');

        $startTime = microtime(true);
        $results = [];

        try {
            $cdnService = new CDNService;

            // Verificar conectividad CDN
            $this->line('  • Verificando conectividad CDN...');
            $connectivity = $cdnService->testCDNConnectivity();
            $results['connectivity'] = $connectivity['base_url_reachable'] ? 'OK' : 'ERROR';

            // Precargar assets críticos
            $this->line('  • Precargando assets críticos...');
            $preloadResult = $cdnService->preloadCriticalAssets();
            $successCount = count(array_filter($preloadResult, fn ($r) => $r['status'] === 'preloaded'));
            $results['preload_assets'] = $successCount > 0 ? 'OK' : 'ERROR';
            $results['preloaded_count'] = $successCount;

            // Obtener estadísticas CDN
            $this->line('  • Obteniendo estadísticas CDN...');
            $stats = $cdnService->getCDNStats();
            $results['cdn_stats'] = $stats;

            // Optimizar configuración si es necesario
            if ($stats['enabled'] && $stats['cache_hit_rate'] < 80) {
                $this->line('  • Optimizando configuración CDN...');
                $configResult = $cdnService->configureCDN([
                    'enabled' => true,
                    'base_url' => $stats['base_url'],
                ]);
                $results['config_optimization'] = $configResult ? 'OK' : 'ERROR';
            }

            $results['execution_time'] = round(microtime(true) - $startTime, 2);
            $results['status'] = 'completed';

            $this->info('✅ Optimización de CDN completada');

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            $this->error('❌ Error en optimización de CDN: '.$e->getMessage());
            Log::error('CDN optimization failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Limpiar cache antiguo
     */
    private function cleanOldCache(): void
    {
        // Limpiar cache de más de 24 horas
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys('archivemaster:*');

            foreach ($keys as $key) {
                $ttl = $redis->ttl($key);
                if ($ttl > 86400) { // Más de 24 horas
                    $redis->del($key);
                }
            }
        }
    }

    /**
     * Verificar conexión Redis
     */
    private function checkRedisConnection(): bool
    {
        try {
            Cache::put('test_connection', 'ok', 10);

            return Cache::get('test_connection') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Analizar rendimiento de tablas
     */
    private function analyzeTablePerformance(): array
    {
        $tables = ['documents', 'users', 'workflow_histories', 'categories', 'tags'];
        $stats = [];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $stats[$table] = [
                    'row_count' => $count,
                    'status' => $count > 10000 ? 'needs_optimization' : 'ok',
                ];
            } catch (\Exception $e) {
                $stats[$table] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    /**
     * Optimizar tablas MySQL
     */
    private function optimizeMySQLTables(): void
    {
        $tables = ['documents', 'users', 'workflow_histories', 'categories', 'tags'];

        foreach ($tables as $table) {
            try {
                DB::statement("OPTIMIZE TABLE {$table}");
            } catch (\Exception $e) {
                Log::warning("Could not optimize table {$table}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Limpiar logs antiguos
     */
    private function cleanOldLogs(): void
    {
        // Limpiar activity log de más de 90 días
        DB::table('activity_log')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        // Limpiar notificaciones de más de 30 días
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
        }
    }

    /**
     * Verificar integridad de índices
     */
    private function checkIndexIntegrity(): array
    {
        $stats = [];

        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                $indexes = DB::select("
                    SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME IN ('documents', 'users', 'workflow_histories')
                    ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
                ");

                $stats['total_indexes'] = count($indexes);
                $stats['status'] = 'ok';
            } else {
                $stats['status'] = 'skipped_non_mysql';
            }
        } catch (\Exception $e) {
            $stats['status'] = 'error';
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Limpiar archivos temporales
     */
    private function cleanTemporaryFiles(): void
    {
        // Limpiar directorio temporal de Laravel
        $tempFiles = Storage::disk('local')->files('temp');
        foreach ($tempFiles as $file) {
            Storage::disk('local')->delete($file);
        }

        // Limpiar cache de vistas compiladas antiguas
        $viewPath = storage_path('framework/views');
        if (is_dir($viewPath)) {
            $files = glob($viewPath.'/*.php');
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-7 days')) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Obtener estadísticas de almacenamiento
     */
    private function getStorageStatistics(): array
    {
        $stats = [];

        try {
            // Tamaño del directorio storage
            $storageSize = $this->getDirectorySize(storage_path('app'));
            $stats['storage_size_mb'] = round($storageSize / 1024 / 1024, 2);

            // Tamaño del directorio public
            $publicSize = $this->getDirectorySize(public_path());
            $stats['public_size_mb'] = round($publicSize / 1024 / 1024, 2);

            // Espacio libre en disco
            $archivePath = config('filesystems.disks.archive.root') ?: storage_path('app');
            $freeSpace = disk_free_space($archivePath);
            $stats['free_space_gb'] = round($freeSpace / 1024 / 1024 / 1024, 2);

            $stats['status'] = 'ok';

        } catch (\Exception $e) {
            $stats['status'] = 'error';
            $stats['error'] = $e->getMessage();
        }

        return $stats;
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
     * Mostrar resumen de optimizaciones
     */
    private function showOptimizationSummary(array $optimizations, float $totalTime): void
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE OPTIMIZACIONES');
        $this->line('═══════════════════════════════════════');

        foreach ($optimizations as $type => $result) {
            $status = $result['status'] ?? 'unknown';
            $time = $result['execution_time'] ?? 0;

            $statusIcon = match ($status) {
                'completed' => '✅',
                'error' => '❌',
                default => '⚠️'
            };

            $this->line("$statusIcon ".ucfirst($type)." - {$time}s");

            // Mostrar detalles específicos
            if ($type === 'files' && isset($result['documents_compression'])) {
                $compression = $result['documents_compression'];
                $this->line("   • Archivos procesados: {$compression['processed']}");
                $this->line("   • Espacio ahorrado: {$compression['total_saved_mb']} MB");
            }

            if ($type === 'database' && isset($result['table_analysis'])) {
                $tables = count($result['table_analysis']);
                $this->line("   • Tablas analizadas: {$tables}");
            }

            if ($type === 'cdn' && isset($result['preloaded_count'])) {
                $this->line("   • Assets precargados: {$result['preloaded_count']}");
                if (isset($result['cdn_stats']['cache_hit_rate'])) {
                    $hitRate = $result['cdn_stats']['cache_hit_rate'];
                    $this->line("   • Hit rate CDN: {$hitRate}%");
                }
            }

            if ($type === 'cache' && isset($result['redis_optimization'])) {
                $redisStatus = $result['redis_optimization'];
                $this->line("   • Optimización Redis: {$redisStatus}");
            }

            if ($status === 'error' && isset($result['error'])) {
                $this->line("   • Error: {$result['error']}");
            }
        }

        $this->line('═══════════════════════════════════════');
        $this->info("⏱️  Tiempo total: {$totalTime}s");
        $this->info('🎉 Optimización del sistema completada');

        // Recomendaciones
        $this->newLine();
        $this->info('💡 RECOMENDACIONES:');
        $this->line('• Ejecutar esta optimización semanalmente');
        $this->line('• Monitorear el uso de Redis regularmente');
        $this->line('• Revisar logs de errores después de optimizaciones');
        $this->line('• Considerar aumentar memoria si hay muchos archivos');
    }
}
