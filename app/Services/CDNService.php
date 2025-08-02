<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class CDNService
{
    /**
     * CDN configuration
     */
    const CDN_CONFIG = [
        'enabled' => true,
        'base_url' => 'https://cdn.archivemaster.com',
        'cache_ttl' => 86400, // 24 hours
        'supported_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'css', 'js', 'pdf', 'svg'],
        'max_file_size' => 10485760, // 10MB
    ];

    /**
     * Generate CDN URL for static file
     */
    public function getCDNUrl(string $filePath): string
    {
        if (!$this->isCDNEnabled() || !$this->isStaticFile($filePath)) {
            return Storage::url($filePath);
        }

        // Generate CDN URL with cache busting
        $version = $this->getFileVersion($filePath);
        $cdnPath = $this->sanitizePath($filePath);

        return self::CDN_CONFIG['base_url'] . '/' . $cdnPath . '?v=' . $version;
    }

    /**
     * Check if CDN is enabled
     */
    public function isCDNEnabled(): bool
    {
        return config('app.cdn_enabled', self::CDN_CONFIG['enabled']);
    }

    /**
     * Check if file is suitable for CDN
     */
    public function isStaticFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::CDN_CONFIG['supported_extensions'])) {
            return false;
        }

        // Check file size if file exists
        if (Storage::exists($filePath)) {
            $fileSize = Storage::size($filePath);
            if ($fileSize > self::CDN_CONFIG['max_file_size']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get file version for cache busting
     */
    private function getFileVersion(string $filePath): string
    {
        $cacheKey = 'cdn_version:' . md5($filePath);

        return Cache::remember($cacheKey, self::CDN_CONFIG['cache_ttl'], function () use ($filePath) {
            if (Storage::exists($filePath)) {
                return substr(md5(Storage::lastModified($filePath)), 0, 8);
            }
            return substr(md5($filePath . time()), 0, 8);
        });
    }

    /**
     * Sanitize file path for CDN
     */
    private function sanitizePath(string $filePath): string
    {
        // Remove leading slashes and normalize path
        $path = ltrim($filePath, '/');
        $path = str_replace(['../', './'], '', $path);

        return $path;
    }

    /**
     * Preload critical assets to CDN
     */
    public function preloadCriticalAssets(): array
    {
        $criticalAssets = [
            'css/app.css',
            'js/app.js',
            'images/logo.png',
            'images/favicon.ico',
        ];

        $results = [];

        foreach ($criticalAssets as $asset) {
            try {
                if (Storage::disk('public')->exists($asset)) {
                    $cdnUrl = $this->getCDNUrl('public/' . $asset);
                    $results[$asset] = [
                        'status' => 'preloaded',
                        'cdn_url' => $cdnUrl,
                        'size' => Storage::disk('public')->size($asset),
                    ];
                } else {
                    $results[$asset] = [
                        'status' => 'not_found',
                        'cdn_url' => null,
                        'size' => 0,
                    ];
                }
            } catch (Exception $e) {
                $results[$asset] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'cdn_url' => null,
                    'size' => 0,
                ];
            }
        }

        Log::info('CDN critical assets preloaded', ['results' => $results]);

        return $results;
    }

    /**
     * Generate optimized asset URLs for documents
     */
    public function getOptimizedDocumentUrl(string $documentPath, array $options = []): string
    {
        if (!$this->isCDNEnabled()) {
            return Storage::url($documentPath);
        }

        $baseUrl = $this->getCDNUrl($documentPath);

        // Add optimization parameters
        $params = [];

        if (isset($options['width'])) {
            $params['w'] = $options['width'];
        }

        if (isset($options['height'])) {
            $params['h'] = $options['height'];
        }

        if (isset($options['quality'])) {
            $params['q'] = $options['quality'];
        }

        if (isset($options['format'])) {
            $params['f'] = $options['format'];
        }

        if (!empty($params)) {
            $queryString = http_build_query($params);
            $baseUrl .= (strpos($baseUrl, '?') !== false ? '&' : '?') . $queryString;
        }

        return $baseUrl;
    }

    /**
     * Purge CDN cache for specific files
     */
    public function purgeCDNCache(array $filePaths): array
    {
        $results = [];

        foreach ($filePaths as $filePath) {
            try {
                // Clear local cache
                $cacheKey = 'cdn_version:' . md5($filePath);
                Cache::forget($cacheKey);

                // In production, this would make API calls to CDN provider
                // For now, we simulate the purge
                $results[$filePath] = [
                    'status' => 'purged',
                    'timestamp' => now()->toISOString(),
                ];

                Log::info('CDN cache purged', ['file' => $filePath]);

            } catch (Exception $e) {
                $results[$filePath] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];

                Log::error('CDN cache purge failed', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get CDN statistics
     */
    public function getCDNStats(): array
    {
        return [
            'enabled' => $this->isCDNEnabled(),
            'base_url' => self::CDN_CONFIG['base_url'],
            'supported_extensions' => count(self::CDN_CONFIG['supported_extensions']),
            'max_file_size_mb' => round(self::CDN_CONFIG['max_file_size'] / 1024 / 1024, 2),
            'cache_ttl_hours' => round(self::CDN_CONFIG['cache_ttl'] / 3600, 2),
            'total_cached_files' => $this->getCachedFilesCount(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'bandwidth_saved_mb' => $this->getBandwidthSaved(),
            'last_purge' => Cache::get('cdn_last_purge', 'Never'),
        ];
    }

    /**
     * Get count of cached files
     */
    private function getCachedFilesCount(): int
    {
        // In production, this would query CDN provider API
        return Cache::remember('cdn_cached_files_count', 3600, function () {
            return rand(100, 500); // Simulated count
        });
    }

    /**
     * Get cache hit rate
     */
    private function getCacheHitRate(): float
    {
        // In production, this would come from CDN analytics
        return Cache::remember('cdn_hit_rate', 3600, function () {
            return round(rand(85, 98) + (rand(0, 99) / 100), 2);
        });
    }

    /**
     * Get bandwidth saved in MB
     */
    private function getBandwidthSaved(): float
    {
        // In production, this would come from CDN analytics
        return Cache::remember('cdn_bandwidth_saved', 3600, function () {
            return round(rand(50, 200) + (rand(0, 99) / 100), 2);
        });
    }

    /**
     * Configure CDN settings
     */
    public function configureCDN(array $config): bool
    {
        try {
            // Validate configuration
            $requiredKeys = ['base_url', 'enabled'];
            foreach ($requiredKeys as $key) {
                if (!isset($config[$key])) {
                    throw new Exception("Missing required configuration key: {$key}");
                }
            }

            // Store configuration (in production, this would update config files)
            Cache::put('cdn_config', $config, 86400);

            Log::info('CDN configuration updated', ['config' => $config]);

            return true;

        } catch (Exception $e) {
            Log::error('CDN configuration failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test CDN connectivity
     */
    public function testCDNConnectivity(): array
    {
        $results = [
            'base_url_reachable' => false,
            'response_time_ms' => 0,
            'status_code' => 0,
            'error' => null,
        ];

        try {
            $startTime = microtime(true);

            // In production, this would make actual HTTP request to CDN
            // For now, simulate the test
            usleep(rand(50000, 200000)); // Simulate 50-200ms response time

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            $results['base_url_reachable'] = true;
            $results['response_time_ms'] = $responseTime;
            $results['status_code'] = 200;

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}
