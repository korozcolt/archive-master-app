<?php

use Symfony\Component\Process\Process;

it('can disable the scheduled OCR backlog processor', function () {
    $process = new Process([
        PHP_BINARY,
        'artisan',
        'schedule:list',
        '--no-ansi',
    ], base_path(), [
        'DOCUMENT_OCR_SCHEDULE_ENABLED' => 'false',
    ]);

    $process->mustRun();

    expect($process->getOutput())->not->toContain('documents:process-ocr');
});

it('keeps the scheduled OCR backlog processor enabled by default', function () {
    $process = new Process([
        PHP_BINARY,
        'artisan',
        'schedule:list',
        '--no-ansi',
    ], base_path(), [
        'DOCUMENT_OCR_SCHEDULE_ENABLED' => 'true',
    ]);

    $process->mustRun();

    expect($process->getOutput())->toContain('documents:process-ocr');
});
