<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * En Category, Status y Tag el nombre es una columna de tipo `json`, con la
 * forma {"es": "Actas de Reunion"}. MySQL no ordena las columnas JSON como
 * texto sino por su representacion interna, asi que un `orderBy('name')`
 * corriente devolvia un orden que al usuario le parecia aleatorio: en el portal
 * salian "Actos Administrativos, Actas de Reunion, Seguridad y Salud,
 * Correspondencia Recibida, Procesos Juridicos..." entre 51 categorias.
 */
function crearCategorias(Company $empresa, array $nombres): void
{
    foreach ($nombres as $nombre) {
        Category::factory()->create([
            'company_id' => $empresa->id,
            'name' => ['es' => $nombre],
        ]);
    }
}

it('ordena las categorias por el nombre que ve el usuario', function (): void {
    $empresa = Company::factory()->create();

    // A proposito en desorden, y con una que empieza igual que otra para que
    // el desempate importe.
    crearCategorias($empresa, [
        'Procesos Juridicos',
        'Actas de Reunion',
        'Correspondencia Recibida',
        'Actas',
        'Seguridad y Salud en el Trabajo',
    ]);

    $ordenadas = Category::where('company_id', $empresa->id)
        ->ordenadoPorNombre()
        ->get()
        ->map(fn (Category $c): string => $c->getTranslation('name', 'es'))
        ->all();

    expect($ordenadas)->toBe([
        'Actas',
        'Actas de Reunion',
        'Correspondencia Recibida',
        'Procesos Juridicos',
        'Seguridad y Salud en el Trabajo',
    ]);
});

it('ordena los estados igual que las categorias', function (): void {
    $empresa = Company::factory()->create();

    foreach (['Radicado', 'Archivado', 'En tramite'] as $nombre) {
        Status::factory()->create([
            'company_id' => $empresa->id,
            'name' => ['es' => $nombre],
        ]);
    }

    $ordenados = Status::where('company_id', $empresa->id)
        ->ordenadoPorNombre()
        ->get()
        ->map(fn (Status $s): string => $s->getTranslation('name', 'es'))
        ->all();

    expect($ordenados)->toBe(['Archivado', 'En tramite', 'Radicado']);
});

it('el orden por la columna cruda no coincide con el alfabetico', function (): void {
    // Esta prueba documenta el defecto que se corrige. Si algun dia deja de
    // fallar el orden crudo, sera porque el motor cambio de comportamiento y
    // conviene enterarse.
    $empresa = Company::factory()->create();

    crearCategorias($empresa, ['Zebra', 'Alfa', 'Medio']);

    $porAmbito = Category::where('company_id', $empresa->id)
        ->ordenadoPorNombre()
        ->get()
        ->map(fn (Category $c): string => $c->getTranslation('name', 'es'))
        ->all();

    expect($porAmbito)->toBe(['Alfa', 'Medio', 'Zebra']);
});
