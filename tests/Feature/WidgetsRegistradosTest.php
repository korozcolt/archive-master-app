<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * El descubrimiento automatico de widgets esta desactivado para no cargar los
 * veinticinco del proyecto en cada peticion. La contrapartida es que la lista
 * del panel tiene que incluir todos los que se usen, no solo los del tablero:
 * Livewire resuelve por ahi tambien los widgets de cabecera de las paginas de
 * recurso.
 *
 * Y un widget que falte no da un aviso: da un error 500. AdvancedSearchStatsWidget
 * quedo fuera al desactivar el descubrimiento y /admin/advanced-searches estuvo
 * roto casi dos meses -abria con 200 y reventaba en la primera peticion de
 * Livewire con "Unable to find component"-. Nadie se entero hasta que un usuario
 * lo reporto.
 */
function widgetsRegistrados(): Illuminate\Support\Collection
{
    return collect(Filament::getPanel('admin')->getWidgets())
        ->map(fn (string $clase): string => class_basename($clase));
}

it('registra en el panel todos los widgets que usan las paginas de recurso', function (): void {
    // Recorrido recursivo de verdad: glob() no expande `**`, y las paginas de
    // recurso viven dos niveles abajo -Resources/XResource/Pages/-. Con glob
    // esta comprobacion no miraba ningun archivo y pasaba en vacio, que se
    // descubrio al quitar el widget a proposito y ver que seguia en verde.
    $archivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Filament/Resources'), FilesystemIterator::SKIP_DOTS)
    );

    $usados = collect(iterator_to_array($archivos))
        ->filter(fn (SplFileInfo $archivo): bool => $archivo->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $archivo): array {
            preg_match_all('/(\w+Widget)::class/', (string) file_get_contents($archivo->getPathname()), $coincidencias);

            return $coincidencias[1] ?? [];
        })
        ->unique()
        ->values();

    // Si esto queda vacio, la comprobacion no esta mirando nada y no vale.
    expect($usados)->not->toBeEmpty('No se encontro ningun widget en las paginas de recurso: '
        .'el recorrido de archivos no esta funcionando');

    $registrados = widgetsRegistrados()->all();
    $sinRegistrar = $usados->reject(fn (string $w): bool => in_array($w, $registrados, true))->all();

    expect($sinRegistrar)->toBe([], 'Estos widgets se usan en paginas de recurso pero no estan en '
        .'AdminPanelProvider::widgets(), asi que la pagina respondera 500 al primer '
        .'refresco de Livewire: '.implode(', ', $sinRegistrar));
});

it('incluye el widget de la busqueda avanzada, que era el que faltaba', function (): void {
    expect(widgetsRegistrados())->toContain('AdvancedSearchStatsWidget');
});

it('cada widget registrado existe de verdad', function (): void {
    $inexistentes = collect(Filament::getPanel('admin')->getWidgets())
        ->reject(fn (string $clase): bool => class_exists($clase))
        ->all();

    expect($inexistentes)->toBe([]);
});
