<?php

declare(strict_types=1);

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Estas pruebas fijan como se traduce lo que escribe el usuario a la consulta
 * que recibe Meilisearch. No comprueban los resultados -eso depende del indice
 * real, no del motor de coleccion que usan las pruebas- sino la traduccion, que
 * es donde estaba el defecto: un numero de licitacion sin comillas se partia en
 * pedazos y arrastraba medio archivo.
 */
it('deja intacta una busqueda de palabras corrientes', function (): void {
    $busqueda = Document::search('acta de inicio');

    expect($busqueda->query)->toBe('acta de inicio')
        ->and($busqueda->options['matchingStrategy'])->toBe('frequency');
});

it('entrecomilla un identificador para que no se parta en pedazos', function (): void {
    $busqueda = Document::search('LP-ADS-001-2024');

    expect($busqueda->query)->toBe('"LP-ADS-001-2024"')
        ->and($busqueda->options['matchingStrategy'])->toBe('all');
});

it('respeta las comillas que el usuario ya puso a mano', function (): void {
    expect(Document::search('"LP-ADS-001-2024"')->query)->toBe('"LP-ADS-001-2024"');
});

it('entrecomilla cada identificador cuando hay varios', function (): void {
    expect(Document::search('LP-ADS-001-2024 G100-1395-2024')->query)
        ->toBe('"LP-ADS-001-2024" "G100-1395-2024"');
});

it('acota por titulo las palabras que acompanan al identificador', function (): void {
    $busqueda = Document::search('LP-ADS-001-2024 CONTRATO');

    // El identificador va como frase; CONTRATO no se anade a la consulta de
    // texto porque en un expediente de contratacion todos los documentos
    // mencionan esa palabra en su contenido y no filtraria nada. Se aplica
    // como restriccion sobre los titulos.
    expect($busqueda->query)->toBe('"LP-ADS-001-2024"')
        ->and($busqueda->whereIns)->toHaveKey('id');
});

it('trata como unidad cualquier palabra compuesta con guion, lleve digitos o no', function (): void {
    // "pre-contractual" sin comillas se partiria en "pre" y "contractual", y
    // esta segunda sale en miles de documentos. Como unidad busca lo que el
    // usuario escribio.
    expect(Document::search('pre-contractual')->query)->toBe('"pre-contractual"');
});

it('reconoce identificadores de las formas usadas en el archivo', function (string $identificador): void {
    expect(Document::search($identificador)->query)->toBe('"'.$identificador.'"');
})->with([
    'licitacion' => 'LP-ADS-001-2024',
    'comunicacion' => 'G100-1395-2024',
    'radicado' => 'R-2464',
    'numero de documento' => 'DOC-AGU-20260713094648-5265',
    'codigo de barras' => 'DOCAGU-20260701171657-E54E',
    'palabra compuesta' => 'pre-contractual',
]);

it('separa el identificador de las palabras sueltas que lo acompanan', function (): void {
    $busqueda = Document::search('acta LP-ADS-001-2024 inicio');

    expect($busqueda->query)->toBe('"LP-ADS-001-2024"')
        ->and($busqueda->whereIns)->toHaveKey('id');
});

it('no falla con una consulta vacia', function (): void {
    expect(Document::search('')->query)->toBe('');
});
