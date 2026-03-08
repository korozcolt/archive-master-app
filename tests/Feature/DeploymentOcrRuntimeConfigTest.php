<?php

it('installs ocr runtime dependencies in nixpacks', function () {
    $nixpacksConfig = file_get_contents(base_path('nixpacks.toml'));

    expect($nixpacksConfig)
        ->toContain('[phases.setup]')
        ->toContain('poppler-utils')
        ->toContain('tesseract-ocr')
        ->toContain('tesseract-ocr-eng')
        ->toContain('tesseract-ocr-spa');
});

it('runs the queue worker with document processing queues enabled', function () {
    $runtimeScript = file_get_contents(base_path('scripts/run-runtime-services.sh'));

    expect($runtimeScript)
        ->toContain('QUEUE_WORKER_QUEUES="${QUEUE_WORKER_QUEUES:-document-processing,notifications,default,ai-processing}"')
        ->toContain('QUEUE_WORKER_CMD="${QUEUE_WORKER_CMD:-php artisan queue:work --sleep=1 --tries=3 --timeout=120 --queue=${QUEUE_WORKER_QUEUES}}"');
});
