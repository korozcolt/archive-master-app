<?php

namespace App\Console\Commands;

use App\Services\CDNService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CDNManage extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cdn:manage
                            {action : Action to perform (preload, purge, stats, test, configure)}
                            {--files=* : Files to purge (for purge action)}
                            {--url= : CDN base URL (for configure action)}
                            {--enabled= : Enable/disable CDN (for configure action)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage CDN operations (preload, purge, stats, test, configure)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $cdnService = new CDNService();

        return match ($action) {
            'preload' => $this->preloadAssets($cdnService),
            'purge' => $this->purgeCache($cdnService),
            'stats' => $this->showStats($cdnService),
            'test' => $this->testConnectivity($cdnService),
            'configure' => $this->configureCDN($cdnService),
            default => $this->showHelp(),
        };
    }

    /**
     * Preload critical assets to CDN
     */
    private function preloadAssets(CDNService $cdnService): int
    {
        $this->info('🚀 Preloading critical assets to CDN...');

        try {
            $results = $cdnService->preloadCriticalAssets();

            $this->newLine();
            $this->info('📊 Preload Results:');
            $this->line('═══════════════════════════════════════');

            $totalSize = 0;
            $successCount = 0;

            foreach ($results as $asset => $result) {
                $status = $result['status'];
                $size = $result['size'] ?? 0;
                $totalSize += $size;

                $statusIcon = match ($status) {
                    'preloaded' => '✅',
                    'not_found' => '⚠️',
                    'error' => '❌',
                    default => '❓'
                };

                $sizeFormatted = $this->formatBytes($size);
                $this->line("{$statusIcon} {$asset} - {$sizeFormatted}");

                if ($status === 'preloaded') {
                    $successCount++;
                    $this->line("   CDN URL: {$result['cdn_url']}");
                } elseif ($status === 'error') {
                    $this->line("   Error: {$result['error']}");
                }

                if ($status !== 'error') {
                    $this->newLine();
                }
            }

            $this->line('═══════════════════════════════════════');
            $this->info("✅ Preloaded {$successCount}/" . count($results) . " assets");
            $this->info("📦 Total size: " . $this->formatBytes($totalSize));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error preloading assets: ' . $e->getMessage());
            Log::error('CDN preload failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Purge CDN cache for specific files
     */
    private function purgeCache(CDNService $cdnService): int
    {
        $files = $this->option('files');

        if (empty($files)) {
            $this->error('❌ No files specified for purging. Use --files option.');
            $this->line('Example: php artisan cdn:manage purge --files=css/app.css --files=js/app.js');
            return self::FAILURE;
        }

        $this->info('🧹 Purging CDN cache for specified files...');

        try {
            $results = $cdnService->purgeCDNCache($files);

            $this->newLine();
            $this->info('📊 Purge Results:');
            $this->line('═══════════════════════════════════════');

            $successCount = 0;

            foreach ($results as $file => $result) {
                $status = $result['status'];
                $statusIcon = $status === 'purged' ? '✅' : '❌';

                $this->line("{$statusIcon} {$file}");

                if ($status === 'purged') {
                    $successCount++;
                    $this->line("   Purged at: {$result['timestamp']}");
                } else {
                    $this->line("   Error: {$result['error']}");
                }

                $this->newLine();
            }

            $this->line('═══════════════════════════════════════');
            $this->info("✅ Purged {$successCount}/" . count($results) . " files");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error purging cache: ' . $e->getMessage());
            Log::error('CDN purge failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Show CDN statistics
     */
    private function showStats(CDNService $cdnService): int
    {
        $this->info('📊 CDN Statistics');
        $this->line('═══════════════════════════════════════');

        try {
            $stats = $cdnService->getCDNStats();

            $this->line("Status: " . ($stats['enabled'] ? '<fg=green>✅ Enabled</>' : '<fg=red>❌ Disabled</>'));
            $this->line("Base URL: <fg=yellow>{$stats['base_url']}</>");
            $this->line("Supported Extensions: <fg=yellow>{$stats['supported_extensions']}</>");
            $this->line("Max File Size: <fg=yellow>{$stats['max_file_size_mb']} MB</>");
            $this->line("Cache TTL: <fg=yellow>{$stats['cache_ttl_hours']} hours</>");

            $this->newLine();
            $this->info('📈 Performance Metrics:');
            $this->line("Total Cached Files: <fg=yellow>{$stats['total_cached_files']}</>");
            $this->line("Cache Hit Rate: <fg=yellow>{$stats['cache_hit_rate']}%</>");
            $this->line("Bandwidth Saved: <fg=yellow>{$stats['bandwidth_saved_mb']} MB</>");
            $this->line("Last Purge: <fg=yellow>{$stats['last_purge']}</>");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error getting stats: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Test CDN connectivity
     */
    private function testConnectivity(CDNService $cdnService): int
    {
        $this->info('🔍 Testing CDN connectivity...');

        try {
            $results = $cdnService->testCDNConnectivity();

            $this->newLine();
            $this->info('📊 Connectivity Test Results:');
            $this->line('═══════════════════════════════════════');

            if ($results['base_url_reachable']) {
                $this->line("Status: <fg=green>✅ Reachable</>");
                $this->line("Response Time: <fg=yellow>{$results['response_time_ms']} ms</>");
                $this->line("Status Code: <fg=yellow>{$results['status_code']}</>");
            } else {
                $this->line("Status: <fg=red>❌ Unreachable</>");
                if ($results['error']) {
                    $this->line("Error: <fg=red>{$results['error']}</>");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error testing connectivity: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Configure CDN settings
     */
    private function configureCDN(CDNService $cdnService): int
    {
        $url = $this->option('url');
        $enabled = $this->option('enabled');

        if (!$url && !$enabled) {
            $this->error('❌ No configuration options provided.');
            $this->line('Use --url and/or --enabled options.');
            $this->line('Example: php artisan cdn:manage configure --url=https://cdn.example.com --enabled=true');
            return self::FAILURE;
        }

        $this->info('⚙️ Configuring CDN settings...');

        try {
            $config = [];

            if ($url) {
                $config['base_url'] = $url;
            }

            if ($enabled !== null) {
                $config['enabled'] = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
            }

            $success = $cdnService->configureCDN($config);

            if ($success) {
                $this->info('✅ CDN configuration updated successfully!');

                $this->newLine();
                $this->info('📋 New Configuration:');
                foreach ($config as $key => $value) {
                    $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                    $this->line("   {$key}: <fg=yellow>{$displayValue}</>");
                }

                return self::SUCCESS;
            } else {
                $this->error('❌ Failed to update CDN configuration');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error configuring CDN: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Show help information
     */
    private function showHelp(): int
    {
        $this->error('❌ Invalid action specified.');
        $this->newLine();

        $this->info('📋 Available Actions:');
        $this->line('   preload   - Preload critical assets to CDN');
        $this->line('   purge     - Purge specific files from CDN cache');
        $this->line('   stats     - Show CDN statistics');
        $this->line('   test      - Test CDN connectivity');
        $this->line('   configure - Configure CDN settings');

        $this->newLine();
        $this->info('📝 Usage Examples:');
        $this->line('   php artisan cdn:manage preload');
        $this->line('   php artisan cdn:manage purge --files=css/app.css --files=js/app.js');
        $this->line('   php artisan cdn:manage stats');
        $this->line('   php artisan cdn:manage test');
        $this->line('   php artisan cdn:manage configure --url=https://cdn.example.com --enabled=true');

        return self::FAILURE;
    }

    /**
     * Format bytes to human readable format
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
}
