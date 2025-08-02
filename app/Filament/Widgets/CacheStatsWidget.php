<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class CacheStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $cacheInfo = CacheService::getCacheInfo();
        $cacheStats = CacheService::getCacheStats();

        return [
            Stat::make('Cache Driver', $cacheInfo['driver'])
                ->description($cacheInfo['redis_connected'] ? 'Redis conectado' : 'Redis desconectado')
                ->descriptionIcon($cacheInfo['redis_connected'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($cacheInfo['redis_connected'] ? 'success' : 'danger'),

            Stat::make('Hit Rate', $cacheStats['hit_rate'] . '%')
                ->description('Tasa de aciertos del cache')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($cacheStats['hit_rate'] > 80 ? 'success' : ($cacheStats['hit_rate'] > 60 ? 'warning' : 'danger')),

            Stat::make('Total Keys', number_format($cacheStats['total_keys']))
                ->description('Claves almacenadas')
                ->descriptionIcon('heroicon-o-key')
                ->color('info'),

            Stat::make('Memory Usage', $cacheStats['memory_usage_mb'] . ' MB')
                ->description('Memoria utilizada')
                ->descriptionIcon('heroicon-o-cpu-chip')
                ->color($cacheStats['memory_usage_mb'] < 100 ? 'success' : ($cacheStats['memory_usage_mb'] < 200 ? 'warning' : 'danger')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']);
    }
}
