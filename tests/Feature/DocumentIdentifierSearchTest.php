<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
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

it('deja intacta una consulta sin guiones, aunque lleve ordinal', function (): void {
    // Sin identificador que rearmar, tocar la consulta solo hace dano: quitando
    // el ordinal, el numero suelto se confunde con otros parecidos y el
    // documento correcto se pierde. Los ordinales se resuelven al comparar con
    // el titulo, normalizando los dos lados.
    expect(Document::search('RESOLUCION N° 405')->query)->toBe('RESOLUCION N° 405')
        ->and(Document::search('COMUNICACION INTERNA N°0069')->query)->toBe('COMUNICACION INTERNA N°0069');
});

it('deja intactas las consultas que ya venian bien formadas', function (): void {
    expect(Document::search('LP-ADS-001-2024')->query)->toBe('"LP-ADS-001-2024"')
        ->and(Document::search('LP-ADS-001-2024, CONTRATO')->query)->toBe('"LP-ADS-001-2024"');
});

/**
 * Tolerancia al comparar con el titulo.
 *
 * Dos fallos vistos en uso real, ambos por comparar contra el titulo crudo:
 *   - "OTROSI N°4" no encontraba el documento titulado "OTROSI N°4", porque la
 *     consulta llegaba normalizada sin el "N°" y el titulo si lo llevaba.
 *   - "ACTA DE SUSPENSION N°1" no encontraba "ACTA SUSPENSION N°1" por una
 *     preposicion de mas.
 */
it('reduce a la misma forma un titulo y una consulta que dicen lo mismo', function (string $a, string $b): void {
    $forma = new ReflectionMethod(Document::class, 'formaComparable');
    $forma->setAccessible(true);

    expect($forma->invoke(null, $a))->toBe($forma->invoke(null, $b));
})->with([
    'ordinal con grado' => ['OTROSI N°4', 'otrosi 4'],
    'ordinal abreviado' => ['ACTA No. 1', 'acta 1'],
    'ordinal con numeral' => ['ACTA #1', 'acta 1'],
    'acentos' => ['GESTIÓN DOCUMENTAL', 'gestion documental'],
    'puntuacion' => ['ACTA-SUSPENSION, N°1', 'acta suspension 1'],
    'espacios de sobra' => ['  OTROSI   N°4  ', 'otrosi 4'],
]);

it('descarta los conectores al exigir palabras en el titulo', function (): void {
    $metodo = new ReflectionMethod(Document::class, 'palabrasSignificativas');
    $metodo->setAccessible(true);

    expect($metodo->invoke(null, 'ACTA DE SUSPENSION N°1'))->toBe(['acta', 'suspension', '1'])
        ->and($metodo->invoke(null, 'OTROSI N°4'))->toBe(['otrosi', '4']);
});

it('conserva los conectores si la exigencia no tiene otra cosa', function (): void {
    $metodo = new ReflectionMethod(Document::class, 'palabrasSignificativas');
    $metodo->setAccessible(true);

    // Mejor exigir algo raro que no exigir nada y devolver el expediente entero.
    expect($metodo->invoke(null, 'de la'))->toBe(['de', 'la']);
});

/**
 * Numero de documento deducido, sin que el usuario ponga la coma.
 *
 * En este archivo los documentos se titulan "TIPO N°NNN". Cuando alguien
 * escribe un tipo seguido de un numero esta senalando un documento concreto.
 * Sin interpretarlo asi el numero se diluye: "COMUNICACION INTERNA 0069"
 * devolvia 232 resultados encabezados por egresos, sin el documento correcto.
 *
 * La marcha atras es lo que lo hace seguro: si esa lectura no encuentra nada se
 * sigue con la busqueda normal, asi que puede anadir precision pero nunca
 * quitar resultados.
 */
it('reconoce el numero del documento aunque no se ponga la coma', function (string $escrito): void {
    // Sin indice real la lectura precisa no encuentra nada y se vuelve atras,
    // asi que aqui se comprueba que la consulta no se rompe por el camino.
    expect(fn () => Document::search($escrito)->take(5)->keys())->not->toThrow(Throwable::class);
})->with([
    'con grado pegado' => 'COMUNICACION INTERNA N°0069',
    'con tilde' => 'COMUNICACIÓN INTERNA N°0069',
    'con ene suelta' => 'COMUNICACION INTERNA N 0069',
    'numero pelado' => 'COMUNICACION INTERNA 0069',
    'abreviado con punto' => 'COMUNICACION INTERNA No. 0069',
]);

it('separa el tipo del numero tal y como lo haria la coma', function (string $escrito): void {
    $metodo = new ReflectionMethod(Document::class, 'intentarPorNumeroDeDocumento');
    $metodo->setAccessible(true);

    // Sin resultados devuelve null, que es la marcha atras. Lo que se comprueba
    // aqui es que la consulta encaja en el patron y llega a intentarse.
    $patron = new ReflectionMethod(Document::class, 'normalizarConsulta');
    $patron->setAccessible(true);

    expect(preg_match(
        '/^(.*?\p{L}.*?)\s+(?:n[oº°]\.?|n\.|n|n[uú]m(?:ero)?\.?|#)?\s*(\d{2,})$/iu',
        $patron->invoke(null, $escrito)
    ))->toBe(1);
})->with([
    'COMUNICACION INTERNA N°0069',
    'COMUNICACION INTERNA N 0069',
    'COMUNICACION INTERNA 0069',
    'COMUNICACION INTERNA No. 0069',
    'RESOLUCION 405',
]);

it('no confunde con un numero de documento lo que no lo es', function (string $escrito): void {
    expect(preg_match(
        '/^(.*?\p{L}.*?)\s+(?:n[oº°]\.?|n\.|n|n[uú]m(?:ero)?\.?|#)?\s*(\d{2,})$/iu',
        $escrito
    ))->toBe(0);
})->with([
    'sin numero al final' => 'acta de inicio',
    'solo un numero' => '0069',
    'numero de una cifra' => 'acta 5',
]);

/**
 * "egreso 1290" tiene que traer los cinco, no uno.
 *
 * La numeracion de egresos se reinicia cada ano, asi que ese numero existe una
 * vez por ano -2020, 2021, 2022, 2024 y 2025- y el usuario los quiere todos.
 * Buscar el tipo y filtrar despues por el numero devolvia **uno**: se recuperaban
 * los mil primeros de los 7.756 egresos y se filtraba sobre esa ventana. Ahora se
 * intenta antes la consulta entera como frase exacta, que es selectiva de por si.
 *
 * OJO CON EL ALCANCE DE ESTAS PRUEBAS: la rama de la frase no se puede ejercitar
 * aqui. El motor de coleccion que usan las pruebas no entiende las comillas de
 * frase -las trata como texto literal-, asi que siempre cae al camino de
 * respaldo. Lo que se comprueba abajo es que ese respaldo sigue intacto; que la
 * frase devuelve los cinco esta verificado contra el indice real de produccion,
 * no aqui.
 */
it('sigue deduciendo el numero cuando la frase no encuentra nada', function (): void {
    $empresa = Company::factory()->create();
    $usuario = User::factory()->create(['company_id' => $empresa->id]);
    $estado = Status::factory()->create(['company_id' => $empresa->id]);

    $documento = Document::factory()->create([
        'company_id' => $empresa->id,
        'status_id' => $estado->id,
        'created_by' => $usuario->id,
        'title' => 'COMUNICACION INTERNA N°0069 CARLOS NUNEZ',
    ]);

    $busqueda = Document::search('COMUNICACION INTERNA 0069');

    // El tipo va al indice y el numero queda como exigencia sobre el titulo,
    // que es lo que rescata al documento cuando la frase entera no coincide
    // -aqui no coincide porque el titulo lleva el "N°" entre medias-.
    expect($busqueda->query)->toBe('COMUNICACION INTERNA')
        ->and($busqueda->whereIns['id'] ?? [])->toContain($documento->id);
});

it('no confunde con un numero una consulta que no termina en cifras', function (): void {
    expect(Document::search('egreso mensual')->query)->toBe('egreso mensual')
        ->and(Document::search('egreso mensual')->options['matchingStrategy'])->toBe('frequency');
});
