<?php

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use PdfStudio\Laravel\Facades\Pdf as PdfStudio;
use Symfony\Component\HttpFoundation\Response;

class PdfStudioPdfRenderer implements PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function downloadView(string $view, array $data, string $filename, array $options = []): Response
    {
        $builder = PdfStudio::view($view)
            ->data($data)
            ->driver((string) ($options['driver'] ?? config('reports.pdf.pdf_studio.driver', 'dompdf')))
            ->format((string) ($options['format'] ?? 'A4'))
            ->landscape((bool) ($options['landscape'] ?? false));

        if (($options['cache_enabled'] ?? false) === true) {
            $builder->cache((int) ($options['cache_ttl'] ?? config('reports.pdf.pdf_studio.cache_ttl', 1800)));
        }

        return $builder->download($filename);
    }
}
