<?php

return [
    'pdf' => [
        'renderer' => env('REPORTS_PDF_RENDERER', 'dompdf'),
        'paper' => env('REPORTS_PDF_PAPER', 'A4'),
        'orientation' => env('REPORTS_PDF_ORIENTATION', 'portrait'),
        'pdf_studio' => [
            'enabled' => env('REPORTS_PDF_STUDIO_ENABLED', false),
            'driver' => env('REPORTS_PDF_STUDIO_DRIVER', 'dompdf'),
            'cache_enabled' => env('REPORTS_PDF_STUDIO_CACHE', false),
            'cache_ttl' => (int) env('REPORTS_PDF_STUDIO_CACHE_TTL', 1800),
        ],
    ],
];
