<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Verificar documentos vencidos diariamente a las 9:00 AM
        $schedule->command('documents:check-overdue --notifications')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/overdue-check.log'));

        // Verificar documentos vencidos cada 4 horas durante horario laboral
        $schedule->command('documents:check-overdue --notifications')
                 ->cron('0 8,12,16,20 * * *')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Limpiar logs antiguos semanalmente
        $schedule->command('log:clear')
                 ->weekly()
                 ->sundays()
                 ->at('02:00');

        // Limpiar actividades antiguas mensualmente
        $schedule->command('activitylog:clean')
                 ->monthly()
                 ->when(function () {
                     return config('activitylog.delete_records_older_than_days', 0) > 0;
                 });

        // Generar reportes automáticos mensualmente
        $schedule->call(function () {
            \Illuminate\Support\Facades\Log::info('Generando reportes mensuales automáticos');
            // Aquí se puede agregar lógica para generar reportes automáticos
        })->monthly()->at('01:00');

        // Verificar integridad de archivos semanalmente
        $schedule->call(function () {
            \Illuminate\Support\Facades\Log::info('Verificando integridad de archivos');
            // Aquí se puede agregar lógica para verificar archivos
        })->weekly()->fridays()->at('23:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}