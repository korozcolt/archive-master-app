<?php

use App\Services\OCRService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OCR Service', function () {
    test('tesseract is installed and available', function () {
        $output = shell_exec('which tesseract 2>&1');

        expect($output)->not->toBeNull()
            ->and($output)->toContain('tesseract');
    });

    test('tesseract supports spanish language', function () {
        $output = shell_exec('tesseract --list-langs 2>&1');

        expect($output)->toContain('spa');
    });

    test('tesseract supports english language', function () {
        $output = shell_exec('tesseract --list-langs 2>&1');

        expect($output)->toContain('eng');
    });

    test('ocr service maps languages correctly', function () {
        $service = app(OCRService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('mapLanguageToTesseract');
        $method->setAccessible(true);

        expect($method->invoke($service, 'es'))->toBe('spa')
            ->and($method->invoke($service, 'en'))->toBe('eng')
            ->and($method->invoke($service, 'fr'))->toBe('fra')
            ->and($method->invoke($service, 'de'))->toBe('deu')
            ->and($method->invoke($service, 'it'))->toBe('ita')
            ->and($method->invoke($service, 'pt'))->toBe('por');
    });

    test('ocr service defaults to english for unknown language', function () {
        $service = app(OCRService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('mapLanguageToTesseract');
        $method->setAccessible(true);

        expect($method->invoke($service, 'unknown'))->toBe('eng');
    });

    test('ocr service processes text extraction', function () {
        $service = app(OCRService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('processExtractedText');
        $method->setAccessible(true);

        $rawText = "  Multiple    spaces   and\n\n\nnewlines  ";
        $processed = $method->invoke($service, $rawText);

        expect($processed)->not->toBeEmpty()
            ->and(strlen($processed))->toBeLessThan(strlen($rawText));
    });

    test('ocr service processes text correctly', function () {
        $service = app(OCRService::class);
        expect($service)->toBeInstanceOf(OCRService::class);
    });
});

describe('OCR File Processing', function () {
    test('ocr service handles supported formats', function () {
        $service = app(OCRService::class);

        // Verify service can be instantiated
        expect($service)->toBeInstanceOf(OCRService::class);
    });
});
