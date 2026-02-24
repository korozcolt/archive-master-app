<?php

use App\Support\FileExtensionIcon;

it('maps common extensions to readable icon metadata', function (): void {
    $pdf = FileExtensionIcon::meta('pdf');
    $docx = FileExtensionIcon::meta('docx');
    $xlsx = FileExtensionIcon::meta('xlsx');
    $png = FileExtensionIcon::meta('png');

    expect($pdf['icon'])->toBe('tni-pdf-o')
        ->and($pdf['label'])->toBe('PDF')
        ->and($docx['icon'])->toBe('tni-doc-o')
        ->and($docx['label'])->toBe('DOCX')
        ->and($xlsx['icon'])->toBe('tni-xls-o')
        ->and($png['icon'])->toBe('tni-png-o');
});

it('maps additional families like audio video archives and design files', function (): void {
    $mp4 = FileExtensionIcon::meta('mp4');
    $mp3 = FileExtensionIcon::meta('mp3');
    $zip = FileExtensionIcon::meta('zip');
    $dwg = FileExtensionIcon::meta('dwg');
    $fig = FileExtensionIcon::meta('fig');

    expect($mp4['icon'])->toBe('tni-mp4-o')
        ->and($mp3['icon'])->toBe('heroicon-o-musical-note')
        ->and($zip['icon'])->toBe('tni-zip-o')
        ->and($dwg['icon'])->toBe('heroicon-o-cube')
        ->and($fig['icon'])->toBe('heroicon-o-swatch');
});

it('returns default metadata for unknown or empty extensions', function (): void {
    $unknown = FileExtensionIcon::meta('weirdext');
    $empty = FileExtensionIcon::meta(null);

    expect($unknown['icon'])->toBe('tni-file-o')
        ->and($unknown['label'])->toBe('WEIRDEXT')
        ->and($empty['icon'])->toBe('tni-file-o')
        ->and($empty['label'])->toBe('ARCHIVO');
});

it('extracts normalized extension from file path', function (): void {
    expect(FileExtensionIcon::extensionFromPath('documents/Contrato-Final.PDF'))->toBe('pdf')
        ->and(FileExtensionIcon::extensionFromPath('views/components/card.blade.php'))->toBe('blade.php')
        ->and(FileExtensionIcon::extensionFromPath(null))->toBe('')
        ->and(FileExtensionIcon::extensionFromPath('sin-extension'))->toBe('');
});
