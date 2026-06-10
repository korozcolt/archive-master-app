<?php

namespace App\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function downloadView(string $view, array $data, string $filename, array $options = []): Response;
}
