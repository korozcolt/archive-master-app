<?php

use App\Services\FileCompressionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificar documentos vencidos diariamente a las 9:00 AM
Schedule::command('documents:notify-overdue')
    ->dailyAt('09:00')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/overdue-notifications.log'));

// Verificar documentos próximos a vencer diariamente a las 8:00 AM
Schedule::command('documents:check-due')
    ->dailyAt('08:00')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/due-notifications.log'));

// Verificar documentos vencidos cada 4 horas durante horario laboral
Schedule::command('documents:notify-overdue')
    ->cron('0 8,12,16,20 * * *')
    ->withoutOverlapping(60)
    ->runInBackground();

// Expirar solicitudes de acceso a documentos aprobadas cuyo plazo venció
Schedule::command('access-requests:expire')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/access-requests-expire.log'));

// Indexar documentos en Scout diariamente a las 2:00 AM
Schedule::command('search:index')
    ->dailyAt('02:00')
    ->withoutOverlapping(180)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/search-index.log'));

// Limpiar notificaciones antiguas semanalmente
Schedule::command('notifications:clean --days=30')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/notifications-clean.log'));

// Limpiar actividades antiguas mensualmente
Schedule::command('activitylog:clean')
    ->monthly()
    ->when(function () {
        return config('activitylog.delete_records_older_than_days', 0) > 0;
    });

// Procesar reportes programados cada 15 minutos
Schedule::command('reports:process-scheduled')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-reports.log'));

// Procesar documentos con OCR de forma continua durante la jornada operativa
//
// El vencimiento del candado es explicito, y no por gusto. Laravel usa 24 horas
// por defecto, asi que si el proceso muere sin soltarlo -corte de luz, un
// despliegue que releva el contenedor a media tanda- el programador se salta el
// OCR durante un dia entero, sin avisar a nadie: la aplicacion responde, las
// busquedas van, los respaldos corren, y el OCR simplemente no avanza. Ocurrio
// tres veces en tres dias y costo unas 18 horas de procesamiento.
//
// 120 minutos, y no menos, porque una tanda de 50 documentos tarda lo que
// tarden: se ha visto una corriendo casi tres horas con escaneos de cientos de
// paginas. Un vencimiento corto dejaria que arrancara una segunda instancia
// sobre los mismos documentos, que es justo lo que este candado evita.
//
// Para la recuperacion rapida esta el vigilante del host (archive-ocr-vigilancia),
// que libera el candado en cuanto comprueba que no hay ningun proceso de OCR
// vivo, sin esperar al vencimiento.
if (config('documents.ocr.schedule_enabled')) {
    Schedule::command('documents:process-ocr --limit=50 --language=spa')
        ->everyFiveMinutes()
        ->withoutOverlapping(120)
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/ocr-processing.log'));
}

// Optimizar rendimiento del sistema semanalmente
Schedule::command('system:optimize-performance --all --force')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping(180)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/system-optimization.log'));

// Monitorear sistema cada hora
Schedule::command('system:monitor --alert')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/system-monitoring.log'));

// Comprimir archivos semanalmente
Schedule::call(function () {
    $compressionService = new FileCompressionService;
    $result = $compressionService->compressExistingFiles('documents', 100);
    Log::info('Compresión automática de archivos completada', $result);
})->weekly()->mondays()->at('02:00');

// Generar reportes automáticos mensualmente
Schedule::call(function () {
    Log::info('Generando reportes mensuales automáticos');
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
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cache-warm.log'));

// Verificar estado del cache cada 6 horas
Schedule::command('cache:status')
    ->cron('0 */6 * * *')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cache-status.log'));

// Precargar assets críticos al CDN diariamente a las 5:00 AM
Schedule::command('cdn:manage preload')
    ->dailyAt('05:00')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cdn-preload.log'));

// Verificar conectividad CDN cada 2 horas
Schedule::command('cdn:manage test')
    ->cron('0 */2 * * *')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cdn-connectivity.log'));
