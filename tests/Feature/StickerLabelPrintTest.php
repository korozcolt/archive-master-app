<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\PhysicalLocation;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\QRCodeService;
use App\Services\StickerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->location = PhysicalLocation::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->archivistRole = Role::firstOrCreate(['name' => 'archive_manager']);
    $this->archivist = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->archivist->assignRole($this->archivistRole);

    $this->document = Document::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->archivist->id,
        'physical_location_id' => $this->location->id,
    ]);
});

// === Servicios de generación de códigos ===

it('generates barcode data url for a document', function () {
    $barcodeService = app(BarcodeService::class);
    $dataUrl = $barcodeService->getAsDataUrl('DOC-TEST-001');

    expect($dataUrl)->toStartWith('data:image/png;base64,');
});

it('generates QR code data url for a document', function () {
    $qrService = app(QRCodeService::class);
    $dataUrl = $qrService->getAsDataUrl('DOC-TEST-001', 200, 'Test Label');

    expect($dataUrl)->toStartWith('data:image/png;base64,');
});

// === StickerService - Generación de datos ===

it('generates sticker data for a document with location', function () {
    $stickerService = app(StickerService::class);
    $data = $stickerService->generateForDocument($this->document);

    expect($data)
        ->toHaveKeys(['type', 'document', 'barcode', 'qrcode', 'title', 'document_number', 'company', 'location', 'created_at'])
        ->and($data['type'])->toBe('document')
        ->and($data['barcode'])->toStartWith('data:image/')
        ->and($data['qrcode'])->toStartWith('data:image/')
        ->and($data['document_number'])->toBe($this->document->document_number)
        ->and($data['location'])->not->toBe('Sin ubicación asignada');
});

it('generates sticker data for a document without location', function () {
    $documentWithoutLocation = Document::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->archivist->id,
        'physical_location_id' => null,
    ]);

    $stickerService = app(StickerService::class);
    $data = $stickerService->generateForDocument($documentWithoutLocation);

    expect($data['location'])->toBe('Sin ubicación asignada');
});

it('generates sticker data for a physical location', function () {
    $stickerService = app(StickerService::class);
    $data = $stickerService->generateForLocation($this->location);

    expect($data)
        ->toHaveKeys(['type', 'location', 'barcode', 'qrcode', 'code', 'full_path', 'capacity', 'company'])
        ->and($data['type'])->toBe('location')
        ->and($data['barcode'])->toStartWith('data:image/')
        ->and($data['qrcode'])->toStartWith('data:image/');
});

// === StickerService - Generación de PDF ===

it('generates PDF sticker for a document with standard template', function () {
    $stickerService = app(StickerService::class);
    $pdf = $stickerService->generatePDFForDocument($this->document, 'standard');

    expect($pdf)->not->toBeEmpty()
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});

it('generates PDF sticker for a document with label template', function () {
    $stickerService = app(StickerService::class);
    $pdf = $stickerService->generatePDFForDocument($this->document, 'label');

    expect($pdf)->not->toBeEmpty()
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});

it('generates PDF sticker for a physical location', function () {
    $stickerService = app(StickerService::class);
    $pdf = $stickerService->generatePDFForLocation($this->location, 'standard');

    expect($pdf)->not->toBeEmpty()
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});

it('generates batch PDF for multiple documents', function () {
    $documents = [
        $this->document,
        Document::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->archivist->id,
            'physical_location_id' => $this->location->id,
        ]),
    ];

    $stickerService = app(StickerService::class);
    $pdf = $stickerService->generateBatchForDocuments($documents, 'standard');

    expect($pdf)->not->toBeEmpty()
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});

// === HTML Preview ===

it('generates HTML preview for a document sticker', function () {
    $stickerService = app(StickerService::class);
    $html = $stickerService->previewDocument($this->document, 'standard');

    expect($html)
        ->toContain($this->document->document_number)
        ->toContain('QR Code')
        ->toContain('Barcode');
});

// === Rutas de Stickers - Archivista autenticado ===

it('allows archivist to preview a document sticker', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.preview', ['document' => $this->document->id]));

    $response->assertSuccessful();
    $response->assertSee($this->document->document_number);
});

it('allows archivist to download a document sticker PDF', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.download', ['document' => $this->document->id]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition');
});

it('allows archivist to download sticker with specific template', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.download', [
            'document' => $this->document->id,
            'template' => 'label',
        ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

it('allows archivist to download sticker with custom options', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.download', [
            'document' => $this->document->id,
            'template' => 'detailed',
            'options' => [
                'include_company' => true,
                'include_date' => true,
            ],
        ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

it('allows archivist to preview a location sticker', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.locations.preview', ['location' => $this->location->id]));

    $response->assertSuccessful();
});

it('allows archivist to download a location sticker PDF', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.locations.download', ['location' => $this->location->id]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

// === Acceso de templates ===

it('returns available sticker templates', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.templates'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'templates' => [
            'standard' => ['name', 'description', 'size'],
            'compact' => ['name', 'description', 'size'],
            'detailed' => ['name', 'description', 'size'],
            'label' => ['name', 'description', 'size'],
        ],
        'default_options',
    ]);
});

// === Impresión directa (sin PDF, vía window.print del navegador) ===

it('allows archivist to access direct print page for document', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.print', [
            'document' => $this->document->id,
            'template' => 'standard',
        ]));

    $response->assertSuccessful();
    $response->assertSee($this->document->document_number);
    $response->assertSee('Imprimir Etiqueta');
    $response->assertSee('window.print()');
});

it('allows archivist to access direct print page with auto_print', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.print', [
            'document' => $this->document->id,
            'template' => 'label',
            'auto_print' => '1',
        ]));

    $response->assertSuccessful();
    $response->assertSee('auto_print');
});

it('allows archivist to access direct print page for location', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.locations.print', [
            'location' => $this->location->id,
            'template' => 'standard',
        ]));

    $response->assertSuccessful();
    $response->assertSee('Imprimir Etiqueta');
    $response->assertSee($this->location->code);
});

// === Seguridad - Usuario no autenticado ===

it('denies unauthenticated access to sticker download', function () {
    $response = $this->get(route('stickers.documents.download', ['document' => $this->document->id]));

    $response->assertRedirect();
});

// === Aislamiento multi-empresa ===

it('denies access to stickers from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherDocument = Document::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.download', ['document' => $otherDocument->id]));

    $response->assertForbidden();
});

// === Templates disponibles ===

it('returns correct template sizes', function () {
    $stickerService = app(StickerService::class);
    $templates = $stickerService->getAvailableTemplates();

    expect($templates)
        ->toHaveKeys(['standard', 'compact', 'detailed', 'label'])
        ->and($templates['standard']['size'])->toBe('50mm x 80mm')
        ->and($templates['label']['size'])->toBe('100mm x 50mm');
});

// === Documento con ubicación física asignada ===

it('includes physical location path in document sticker', function () {
    $stickerService = app(StickerService::class);
    $data = $stickerService->generateForDocument($this->document);

    expect($data['location'])->toBe($this->location->full_path);
});

it('sticker PDF contains document number in downloaded filename', function () {
    $response = $this->actingAs($this->archivist)
        ->get(route('stickers.documents.download', ['document' => $this->document->id]));

    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain($this->document->document_number);
});

// === Generación de todos los templates ===

it('can generate stickers with all available templates', function (string $template) {
    $stickerService = app(StickerService::class);
    $pdf = $stickerService->generatePDFForDocument($this->document, $template);

    expect($pdf)->not->toBeEmpty()
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
})->with(['standard', 'compact', 'detailed', 'label']);

// === TEST DE IMPRESIÓN: Genera un PDF real en disco para enviar a la impresora ===

it('generates a printable sticker PDF file on disk for bluetooth printer test', function () {
    $stickerService = app(StickerService::class);

    // Generar etiqueta estándar con todos los datos del documento archivado
    $pdfStandard = $stickerService->generatePDFForDocument($this->document, 'standard', [
        'include_company' => true,
        'include_date' => true,
    ]);

    // Generar etiqueta formato label (100mm x 50mm) - ideal para impresoras de etiquetas
    $pdfLabel = $stickerService->generatePDFForDocument($this->document, 'label', [
        'include_company' => true,
        'include_date' => true,
    ]);

    // Generar etiqueta de ubicación física
    $pdfLocation = $stickerService->generatePDFForLocation($this->location, 'standard');

    // Guardar los PDFs en storage/app/test-stickers/ para impresión manual
    $outputDir = storage_path('app/test-stickers');
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $timestamp = date('Ymd-His');

    file_put_contents("{$outputDir}/sticker-documento-standard-{$timestamp}.pdf", $pdfStandard);
    file_put_contents("{$outputDir}/sticker-documento-label-{$timestamp}.pdf", $pdfLabel);
    file_put_contents("{$outputDir}/sticker-ubicacion-{$timestamp}.pdf", $pdfLocation);

    // Verificar que los archivos se crearon correctamente
    expect(file_exists("{$outputDir}/sticker-documento-standard-{$timestamp}.pdf"))->toBeTrue()
        ->and(file_exists("{$outputDir}/sticker-documento-label-{$timestamp}.pdf"))->toBeTrue()
        ->and(file_exists("{$outputDir}/sticker-ubicacion-{$timestamp}.pdf"))->toBeTrue()
        ->and(filesize("{$outputDir}/sticker-documento-standard-{$timestamp}.pdf"))->toBeGreaterThan(0)
        ->and(filesize("{$outputDir}/sticker-documento-label-{$timestamp}.pdf"))->toBeGreaterThan(0)
        ->and(filesize("{$outputDir}/sticker-ubicacion-{$timestamp}.pdf"))->toBeGreaterThan(0);

    // Mostrar info para el usuario
    echo "\n";
    echo "  ╔══════════════════════════════════════════════════════════════╗\n";
    echo "  ║  STICKERS DE PRUEBA GENERADOS PARA IMPRESIÓN               ║\n";
    echo "  ╠══════════════════════════════════════════════════════════════╣\n";
    echo "  ║  Documento: {$this->document->document_number}\n";
    echo '  ║  Título: '.Str::limit($this->document->title, 45)."\n";
    echo "  ║  Ubicación: {$this->location->full_path}\n";
    echo "  ║  Empresa: {$this->company->name}\n";
    echo "  ╠══════════════════════════════════════════════════════════════╣\n";
    echo "  ║  Archivos PDF generados en:                                 ║\n";
    echo "  ║  {$outputDir}/\n";
    echo "  ║                                                              ║\n";
    echo "  ║  1. sticker-documento-standard-{$timestamp}.pdf (50x80mm)   \n";
    echo "  ║  2. sticker-documento-label-{$timestamp}.pdf (100x50mm)     \n";
    echo "  ║  3. sticker-ubicacion-{$timestamp}.pdf                      \n";
    echo "  ╠══════════════════════════════════════════════════════════════╣\n";
    echo "  ║  Para imprimir via Bluetooth:                               ║\n";
    echo "  ║  open \"{$outputDir}/sticker-documento-label-{$timestamp}.pdf\"\n";
    echo "  ╚══════════════════════════════════════════════════════════════╝\n";
});
