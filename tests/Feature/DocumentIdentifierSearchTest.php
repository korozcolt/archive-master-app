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

/**
 * La coma separa unidades y todas deben cumplirse. Es la sintaxis que pidio el
 * cliente y la unica que se ofrece: no hay nada que aprender, se escribe igual
 * que una lista, y en un teclado espanol sale sin combinaciones raras -- que no
 * es un detalle menor, porque en los registros hay un usuario intentando
 * agrupar terminos a base de comillas y un signo de mas.
 */
it('trata cada segmento separado por coma como una unidad', function (): void {
    $busqueda = Document::search('LP-2105-2024, ACTA DE INICIO');

    expect($busqueda->query)->toBe('"LP-2105-2024"')
        ->and($busqueda->whereIns)->toHaveKey('id');
});

it('sin coma no obliga a nadie a aprenderla', function (): void {
    // Mismo anclaje; la diferencia es que las palabras se exigen sueltas en vez
    // de como frase, lo que da un resultado menos preciso pero valido.
    $busqueda = Document::search('LP-2105-2024 ACTA DE INICIO');

    expect($busqueda->query)->toBe('"LP-2105-2024"')
        ->and($busqueda->whereIns)->toHaveKey('id');
});

it('ancla en el primer segmento cuando ninguno es un identificador', function (): void {
    $busqueda = Document::search('ACTA DE INICIO, 2024');

    expect($busqueda->query)->toBe('"ACTA DE INICIO"')
        ->and($busqueda->whereIns)->toHaveKey('id');
});

it('ancla en el identificador aunque no vaya primero', function (): void {
    expect(Document::search('ACTA DE INICIO, LP-2105-2024')->query)->toBe('"LP-2105-2024"');
});

it('usa todos los identificadores como anclaje cuando hay varios', function (): void {
    expect(Document::search('LP-2105-2024, G100-1395-2024')->query)
        ->toBe('"LP-2105-2024" "G100-1395-2024"');
});

it('ignora comas sueltas y espacios de sobra', function (): void {
    expect(Document::search('  LP-2105-2024 ,, ,  ACTA DE INICIO ,  ')->query)
        ->toBe('"LP-2105-2024"');
});

it('no cambia nada si la consulta con comas viene vacia', function (): void {
    expect(Document::search(' , , ')->query)->toBe(', ,');
});

/**
 * Normalizacion de identificadores mal escritos.
 *
 * El cliente viene de buscar en Dropbox y espera que el buscador entienda lo
 * que quiso decir. Un usuario escribio literalmente "UO-PSPR-ADS- No. 001-2020"
 * y no obtuvo nada, cuando el expediente existe y tiene seis documentos.
 *
 * Trocear la consulta no sirve: sueltos, "001" devuelve mas de mil documentos
 * de este archivo y "2020" otros tantos. Hay que reconstruir el identificador,
 * no repartirlo.
 */
it('reconstruye un identificador escrito como aparece en el papel', function (string $escrito): void {
    expect(Document::search($escrito)->query)->toBe('"UO-PSPR-ADS-001-2020"');
})->with([
    'con No. y guion suelto' => 'UO-PSPR-ADS- No. 001-2020',
    'con No y todo separado' => 'UO-PSPR-ADS No 001 2020',
    'con espacios entre guiones' => 'UO - PSPR - ADS - 001 - 2020',
    'con simbolo de grado' => 'UO-PSPR-ADS- N° 001-2020',
    'con espacios de sobra' => 'UO-PSPR-ADS-  001-2020',
    'ya bien escrito' => 'UO-PSPR-ADS-001-2020',
]);

it('no destroza una consulta que solo menciona la palabra numero', function (string $escrito): void {
    // El ruido ordinal se quita unicamente cuando va seguido de digitos. Sin esa
    // condicion, buscar el documento titulado "NUMERO DE RADICADO" se quedaria
    // en "de radicado".
    expect(Document::search($escrito)->query)->toBe($escrito);
})->with([
    'numero de radicado',
    'no tengo el numero',
    'acta de inicio',
]);

it('quita el ruido ordinal cuando de verdad precede a un numero', function (): void {
    expect(Document::search('RESOLUCION N° 405')->query)->toBe('RESOLUCION 405');
});

it('deja intactas las consultas que ya venian bien formadas', function (): void {
    expect(Document::search('LP-ADS-001-2024')->query)->toBe('"LP-ADS-001-2024"')
        ->and(Document::search('LP-ADS-001-2024, CONTRATO')->query)->toBe('"LP-ADS-001-2024"');
});
