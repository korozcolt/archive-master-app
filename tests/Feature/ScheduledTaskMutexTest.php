<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Laravel deja los candados de withoutOverlapping() vivos 24 horas si no se le
 * dice otra cosa. Cuando el proceso muere sin soltarlo -un corte de luz, un
 * despliegue que releva el contenedor a media tanda- el programador se salta esa
 * tarea durante un dia entero y nada lo delata.
 *
 * Paso tres veces en tres dias con el OCR y costo unas 18 horas de
 * procesamiento. Estas pruebas existen para que la proxima tarea que alguien
 * anada no herede el mismo defecto en silencio.
 */
const VENCIMIENTO_POR_DEFECTO_DE_LARAVEL = 1440;

it('ninguna tarea programada usa el vencimiento de candado por defecto', function (): void {
    $conDefecto = collect($this->app->make(Schedule::class)->events())
        ->filter(fn ($evento): bool => $evento->withoutOverlapping)
        ->filter(fn ($evento): bool => $evento->expiresAt === VENCIMIENTO_POR_DEFECTO_DE_LARAVEL)
        ->map(fn ($evento): string => $evento->command ?? $evento->description ?? '(sin nombre)')
        ->values()
        ->all();

    expect($conDefecto)->toBe([], 'Estas tareas quedarian bloqueadas 24 horas si su proceso muere: '
        .implode(', ', $conDefecto));
});

it('el candado del OCR aguanta una tanda larga sin permitir solapamiento', function (): void {
    $ocr = collect($this->app->make(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains((string) $evento->command, 'documents:process-ocr'));

    expect($ocr)->not->toBeNull('No hay ninguna tarea de OCR programada');

    // Se han visto tandas de 50 documentos corriendo casi tres horas con
    // escaneos de cientos de paginas. Un vencimiento corto dejaria arrancar una
    // segunda instancia sobre los mismos documentos.
    expect($ocr->expiresAt)->toBeGreaterThanOrEqual(60)
        ->and($ocr->expiresAt)->toBeLessThan(VENCIMIENTO_POR_DEFECTO_DE_LARAVEL);
});

it('las tareas frecuentes se recuperan mucho antes que las esporadicas', function (): void {
    $frecuentes = collect($this->app->make(Schedule::class)->events())
        ->filter(fn ($evento): bool => $evento->withoutOverlapping)
        ->filter(fn ($evento): bool => in_array($evento->expression, ['*/5 * * * *', '*/15 * * * *'], true));

    expect($frecuentes)->not->toBeEmpty();

    // Una tarea que corre cada cinco o quince minutos y queda trabada medio dia
    // acumula un atraso que ya no recupera.
    $frecuentes->each(function ($evento): void {
        expect($evento->expiresAt)->toBeLessThanOrEqual(120);
    });
});
