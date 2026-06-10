<?php

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DompdfPdfRenderer implements PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function downloadView(string $view, array $data, string $filename, array $options = []): Response
    {
        $pdf = Pdf::loadView($view, $data);

        if (isset($options['paper'])) {
            $pdf->setPaper($options['paper'], $options['orientation'] ?? 'portrait');
        }

        return $pdf->download($filename);
    }
}
