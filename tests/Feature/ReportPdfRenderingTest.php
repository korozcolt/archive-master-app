<?php

use App\Contracts\PdfRenderer;
use App\Services\Pdf\DompdfPdfRenderer;
use App\Services\Pdf\PdfStudioPdfRenderer;
use App\Services\ReportService;
use PdfStudio\Laravel\Facades\Pdf as PdfStudio;

it('uses dompdf as the default renderer for report downloads', function () {
    config()->set('reports.pdf.renderer', 'dompdf');
    config()->set('reports.pdf.pdf_studio.enabled', false);

    expect(app(PdfRenderer::class))->toBeInstanceOf(DompdfPdfRenderer::class);
});

it('falls back to dompdf when pdf studio pilot is disabled', function () {
    config()->set('reports.pdf.renderer', 'pdf-studio');
    config()->set('reports.pdf.pdf_studio.enabled', false);

    expect(app(PdfRenderer::class))->toBeInstanceOf(DompdfPdfRenderer::class);
});

it('uses pdf studio for report downloads when the pilot is enabled', function () {
    config()->set('reports.pdf.renderer', 'pdf-studio');
    config()->set('reports.pdf.pdf_studio.enabled', true);
    config()->set('reports.pdf.pdf_studio.driver', 'dompdf');

    PdfStudio::fake();

    $response = app(ReportService::class)->generatePDF('documents-by-status', collect(), []);
    $contentDisposition = (string) $response->headers->get('content-disposition');

    preg_match('/filename="?(?<filename>[^";]+)"?/', $contentDisposition, $matches);

    PdfStudio::assertRenderedView('reports.documents-by-status')
        ->assertDownloaded($matches['filename'] ?? 'documents-by-status.pdf')
        ->assertDriverWas('dompdf');

    expect(app(PdfRenderer::class))->toBeInstanceOf(PdfStudioPdfRenderer::class)
        ->and($response->headers->get('content-type'))->toContain('application/pdf');
});
