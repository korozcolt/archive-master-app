<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificar documentos vencidos diariamente a las 9:00 AM
Schedule::command('documents:notify-overdue')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/overdue-notifications.log'));

// Verificar documentos próximos a vencer diariamente a las 8:00 AM
Schedule::command('documents:check-due')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/due-notifications.log'));

// Verificar documentos vencidos cada 4 horas durante horario laboral
Schedule::command('documents:notify-overdue')
    ->cron('0 8,12,16,20 * * *')
    ->withoutOverlapping()
    ->runInBackground();

// Indexar documentos en Scout diariamente a las 2:00 AM
Schedule::command('search:index')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/search-index.log'));

// Limpiar notificaciones antiguas semanalmente
Schedule::command('notifications:clean --days=30')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/notifications-clean.log'));

// Limpiar logs antiguos semanalmente
Schedule::command('log:clear')
    ->weekly()
    ->sundays()
    ->at('02:00');

// Limpiar actividades antiguas mensualmente
Schedule::command('activitylog:clean')
    ->monthly()
    ->when(function () {
        return config('activitylog.delete_records_older_than_days', 0) > 0;
    });

// Procesar reportes programados cada 15 minutos
Schedule::command('reports:process-scheduled')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-reports.log'));

// Procesar documentos con OCR diariamente a las 3:00 AM
Schedule::command('documents:process-ocr --limit=50')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ocr-processing.log'));

// Optimizar rendimiento del sistema semanalmente
Schedule::command('system:optimize-performance --all --force')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/system-optimization.log'));

// Monitorear sistema cada hora
Schedule::command('system:monitor --alert')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/system-monitoring.log'));

// Comprimir archivos semanalmente
Schedule::call(function () {
    $compressionService = new \App\Services\FileCompressionService();
    $result = $compressionService->compressExistingFiles('documents', 100);
    \Illuminate\Support\Facades\Log::info('Compresión automática de archivos completada', $result);
})->weekly()->mondays()->at('02:00');

// Generar reportes automáticos mensualmente
Schedule::call(function () {
    \Illuminate\Support\Facades\Log::info('Generando reportes mensuales automáticos');
    // Aquí se puede agregar lógica para generar reportes automáticos
})->monthly()->at('01:00');

// Verificar integridad de archivos semanalmente
Schedule::call(function () {
    \Illuminate\Support\Facades\Log::info('Verificando integridad de archivos');
    // Aquí se puede agregar lógica para verificar archivos
})->weekly()->sundays()->at('03:00');

// Calentar cache diariamente a las 6:00 AM
Schedule::command('cache:warm')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cache-warm.log'));

// Verificar estado del cache cada 6 horas
Schedule::command('cache:status')
    ->cron('0 */6 * * *')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cache-status.log'));

// Precargar assets críticos al CDN diariamente a las 5:00 AM
Schedule::command('cdn:manage preload')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cdn-preload.log'));

// Verificar conectividad CDN cada 2 horas
Schedule::command('cdn:manage test')
    ->cron('0 */2 * * *')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cdn-connectivity.log'));
