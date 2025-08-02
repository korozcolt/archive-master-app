<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Services\CacheService;

class CacheStatus extends Command
{
    protected $signature = 'cache:status {--detailed : Show detailed cache information}';
    protected $description = 'Show cache system status and Redis connection';

    public function handle()
    {
        $this->info('🔍 Checking Cache System Status...');
        $this->newLine();

        // Verificar configuración básica
        $this->checkBasicConfig();

        // Verificar conexión Redis
        $this->checkRedisConnection();

        // Verificar funcionamiento del cache
        $this->checkCacheOperations();

        if ($this->option('detailed')) {
            $this->showDetailedInfo();
        }

        $this->newLine();
        $this->info('✅ Cache system check completed!');
    }

    private function checkBasicConfig()
    {
        $this->info('📋 Basic Configuration:');

        $driver = config('cache.default');
        $this->line("   Cache Driver: <fg=yellow>{$driver}</>");

        $prefix = config('cache.prefix');
        $this->line("   Cache Prefix: <fg=yellow>{$prefix}</>");

        $redisClient = config('database.redis.client');
        $this->line("   Redis Client: <fg=yellow>{$redisClient}</>");

        $this->newLine();
    }

    private function checkRedisConnection()
    {
        $this->info('🔗 Redis Connection:');

        try {
            $redis = Redis::connection();
            $pong = $redis->ping();

            if ($pong === 'PONG') {
                $this->line('   Status: <fg=green>✅ Connected</fg=green>');

                // Información adicional de Redis
                $info = $redis->info();
                $this->line("   Version: <fg=yellow>{$info['redis_version']}</>");
                $this->line("   Memory Used: <fg=yellow>" . $this->formatBytes($info['used_memory']) . "</>");
                $this->line("   Connected Clients: <fg=yellow>{$info['connected_clients']}</>");

            } else {
                $this->line('   Status: <fg=red>❌ Connection failed</fg=red>');
            }
        } catch (\Exception $e) {
            $this->line('   Status: <fg=red>❌ Error: ' . $e->getMessage() . '</fg=red>');
        }

        $this->newLine();
    }

    private function checkCacheOperations()
    {
        $this->info('⚡ Cache Operations Test:');

        try {
            // Test básico de escritura/lectura
            $testKey = 'cache_test_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);

            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);

            if ($retrieved === $testValue) {
                $this->line('   Write/Read: <fg=green>✅ Working</fg=green>');
            } else {
                $this->line('   Write/Read: <fg=red>❌ Failed</fg=red>');
            }

            // Limpiar test
            Cache::forget($testKey);

            // Test del CacheService
            $stats = CacheService::getCacheInfo();
            if ($stats['redis_connected']) {
                $this->line('   CacheService: <fg=green>✅ Working</fg=green>');
            } else {
                $this->line('   CacheService: <fg=red>❌ Redis not connected</fg=red>');
            }

        } catch (\Exception $e) {
            $this->line('   Operations: <fg=red>❌ Error: ' . $e->getMessage() . '</fg=red>');
        }

        $this->newLine();
    }

    private function showDetailedInfo()
    {
        $this->info('📊 Detailed Cache Information:');

        try {
            // Estadísticas del CacheService
            $stats = CacheService::getCacheStats();
            $this->line("   Hit Rate: <fg=yellow>{$stats['hit_rate']}%</>");
            $this->line("   Total Keys: <fg=yellow>{$stats['total_keys']}</>");
            $this->line("   Memory Usage: <fg=yellow>{$stats['memory_usage_mb']} MB</>");
            $this->line("   Average TTL: <fg=yellow>{$stats['avg_ttl_minutes']} minutes</>");

            // Información de Redis
            $redis = Redis::connection();
            $info = $redis->info();

            $this->newLine();
            $this->line('   Redis Detailed Info:');
            $this->line("     Uptime: <fg=yellow>" . $this->formatUptime($info['uptime_in_seconds']) . "</>");
            $this->line("     Total Commands: <fg=yellow>" . number_format($info['total_commands_processed']) . "</>");
            $this->line("     Keyspace Hits: <fg=yellow>" . number_format($info['keyspace_hits']) . "</>");
            $this->line("     Keyspace Misses: <fg=yellow>" . number_format($info['keyspace_misses']) . "</>");

            if ($info['keyspace_hits'] + $info['keyspace_misses'] > 0) {
                $hitRate = ($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100;
                $this->line("     Hit Rate: <fg=yellow>" . round($hitRate, 2) . "%</>");
            }

        } catch (\Exception $e) {
            $this->line('   <fg=red>Error getting detailed info: ' . $e->getMessage() . '</fg=red>');
        }

        $this->newLine();
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }
}
