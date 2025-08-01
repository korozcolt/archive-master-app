<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificar documentos vencidos diariamente a las 9:00 AM
Schedule::command('documents:check-overdue --notifications')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/overdue-check.log'));

// Verificar documentos vencidos cada 4 horas durante horario laboral
Schedule::command('documents:check-overdue --notifications')
    ->cron('0 8,12,16,20 * * *')
    ->withoutOverlapping()
    ->runInBackground();

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
