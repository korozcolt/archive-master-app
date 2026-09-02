<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used when syncing all models
    | to the search service. You should adjust this based on your needs.
    |
    | Supported: "algolia", "meilisearch", "typesense",
    |            "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    /*
     * Sin prefijo, y a proposito.
     *
     * El unico modelo buscable de la aplicacion es Document, y fija su indice
     * en `documents` con un `searchableAs()` explicito, que no pasa por este
     * prefijo. `scout:sync-index-settings` si lo aplica, asi que con
     * SCOUT_PREFIX definido el comando escribia los ajustes -sinonimos,
     * atributos buscables, atributos filtrables- en un indice
     * `<prefijo>documents` vacio, mientras el indice real con los 45.000
     * documentos se quedaba con lo que hubiera.
     *
     * Costo real: los sinonimos que hacen falta para que "declaraciones"
     * encuentre "DECLARACION DE RENTA" estaban en el repositorio y no en el
     * motor. Cada cliente nuevo heredaba el fallo.
     *
     * No se lee de env para que el fallo no pueda reaparecer configurando la
     * variable en un panel. Separar clientes es cosa de la rama y de su propia
     * instancia de Meilisearch, no de este prefijo.
     */
    'prefix' => '',

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 200,
        'unsearchable' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether to notify the search engine
    | of the user performing the search. This is sometimes useful if the
    | engine supports any analytics based on this application's users.
    |
    | Supported engines: "algolia"
    |
    */

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud hosted
    | search engine which works great with Scout out of the box. Just plug
    | in your application ID and admin API key to get started searching.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [
            // 'users' => [
            //     'searchableAttributes' => ['id', 'name', 'email'],
            //     'attributesForFaceting'=> ['filterOnly(email)'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Meilisearch settings. Meilisearch is an open
    | source search engine with minimal configuration. Below, you can state
    | the host and key information for your own Meilisearch installation.
    |
    | See: https://www.meilisearch.com/docs/learn/configuration/instance_options#all-instance-options
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'documents' => [
                'displayedAttributes' => ['id'],
                // El orden fija la prioridad de relevancia en Meilisearch.
                // Los identificadores exactos van primero (un escaneo de codigo
                // de barras debe encontrar su documento antes que cualquier
                // coincidencia de texto) y el contenido OCR al final, porque es
                // largo y ruidoso.
                'searchableAttributes' => [
                    'title',
                    'document_number',
                    'barcode',
                    'qrcode',
                    'description',
                    'physical_location',
                    'historical_reference_code',
                    'historical_box',
                    'historical_folder',
                    'historical_volume',
                    'historical_keywords_text',
                    'historical_department_name',
                    'historical_year',
                    'historical_shelf',
                    'historical_bay',
                    'historical_box_location',
                    'historical_custody_department',
                    'tags',
                    'category_name',
                    'status_name',
                    'company_name',
                    'branch_name',
                    'department_name',
                    'creator_name',
                    'assignee_name',
                    'content',
                ],
                'filterableAttributes' => [
                    'id',
                    'company_id',
                    'category_id',
                    'status_id',
                    'created_by',
                    'assigned_to',
                    'archive_phase',
                    'priority',
                    'is_confidential',
                    'is_archived',
                    // Filtrable ademas de buscable, que es la diferencia entre
                    // "documentos que mencionan 2024" y "documentos de 2024".
                    // `received_at` no sirve para esto: guarda la fecha de carga
                    // y vale 2026 para los 45.306.
                    'historical_year',
                ],
                'sortableAttributes' => [
                    'created_at',
                    'updated_at',
                    'received_at',
                    'due_date',
                ],
                'typoTolerance' => [
                    'disableOnAttributes' => ['content'],
                ],
                /*
                 * Sinonimos: plurales y equivalencias de los tipos documentales.
                 *
                 * Estaban configurados directamente en el indice y no en el
                 * repositorio, asi que nadie podia revisarlos y una instancia
                 * nueva no los heredaba. Se traen aqui para que `scout:sync-index-settings`
                 * los mantenga alineados en cada despliegue.
                 *
                 * Hacen falta porque sin ellos el plural no encuentra nada: los
                 * titulos dicen "DECLARACION DE RENTA" y nadie escribio nunca
                 * "DECLARACIONES" en uno. Buscando el plural, la tolerancia a
                 * erratas emparejaba "DECLARACIONES" con "ACLARACION" y devolvia
                 * documentos sin relacion. La entrada `declaracion` faltaba y era
                 * justo la que pedia el cliente.
                 *
                 * La lista es curada a proposito. Generar plurales por regla
                 * produce basura -"presupuestals", "estudioss", "documentoss"-
                 * porque muchas de las palabras frecuentes ya son plurales o son
                 * adjetivos.
                 */
                'synonyms' => [
                    'acta' => ['actas'],
                    'actas' => ['acta'],
                    'anexo' => ['anexos'],
                    'anexos' => ['anexo'],
                    'cdp' => ['certificado de disponibilidad presupuestal'],
                    'certificacion' => ['certificaciones', 'certificado', 'certificados'],
                    'certificaciones' => ['certificacion', 'certificado', 'certificados'],
                    'certificado' => ['certificados', 'certificacion', 'certificaciones'],
                    'certificados' => ['certificado', 'certificacion', 'certificaciones'],
                    'circular' => ['circulares'],
                    'circulares' => ['circular'],
                    'citacion' => ['citaciones'],
                    'citaciones' => ['citacion'],
                    'comprobante' => ['comprobantes'],
                    'comprobantes' => ['comprobante'],
                    'comunicacion' => ['comunicaciones'],
                    'comunicaciones' => ['comunicacion'],
                    'contrato' => ['contratos'],
                    'contratos' => ['contrato'],
                    'declaracion' => ['declaraciones'],
                    'declaraciones' => ['declaracion'],
                    'egreso' => ['egresos'],
                    'egresos' => ['egreso'],
                    'factura' => ['facturas'],
                    'facturas' => ['factura'],
                    'informe' => ['informes'],
                    'informes' => ['informe'],
                    'invitacion' => ['invitaciones'],
                    'invitaciones' => ['invitacion'],
                    'memorando' => ['memorandos'],
                    'memorandos' => ['memorando'],
                    'oficio' => ['oficios'],
                    'oficios' => ['oficio'],
                    'poliza' => ['polizas'],
                    'polizas' => ['poliza'],
                    'pqrs' => ['peticiones quejas reclamos sugerencias'],
                    'propuesta' => ['propuestas'],
                    'propuestas' => ['propuesta'],
                    'resolucion' => ['resoluciones'],
                    'resoluciones' => ['resolucion'],
                    'solicitud' => ['solicitudes'],
                    'solicitudes' => ['solicitud'],
                ],
                'searchCutoffMs' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Typesense settings. Typesense is an open
    | source search engine using minimal configuration. Below, you will
    | state the host, key, and schema configuration for the instance.
    |
    */

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        // 'max_total_results' => env('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'model-settings' => [
            // User::class => [
            //     'collection-schema' => [
            //         'fields' => [
            //             [
            //                 'name' => 'id',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'name',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'created_at',
            //                 'type' => 'int64',
            //             ],
            //         ],
            //         'default_sorting_field' => 'created_at',
            //     ],
            //     'search-parameters' => [
            //         'query_by' => 'name'
            //     ],
            // ],
        ],
    ],

];
