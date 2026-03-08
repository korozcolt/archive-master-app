<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Services\OCRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function makeOcrDocument(array $overrides = []): Document
{
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    return Document::factory()->create(array_merge([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'metadata' => [],
    ], $overrides));
}

it('processes each document using its stored file path and preserves distinct extracted content', function () {
    Storage::fake('local');
    Queue::fake();

    $firstDocument = makeOcrDocument([
        'title' => 'OCR Documento A',
        'file_path' => 'documents/company-a/doc-a.pdf',
    ]);

    $secondDocument = makeOcrDocument([
        'title' => 'OCR Documento B',
        'file_path' => 'documents/company-b/doc-b.pdf',
    ]);

    Storage::disk('local')->put($firstDocument->file_path, 'pdf-a');
    Storage::disk('local')->put($secondDocument->file_path, 'pdf-b');

    mock(OCRService::class, function (MockInterface $mock) use ($firstDocument, $secondDocument): void {
        $mock->shouldReceive('isTesseractAvailable')->once()->andReturnFalse();
        $mock->shouldReceive('processFile')->once()->with($firstDocument->file_path, 'spa')->andReturn([
            'success' => true,
            'extracted_text' => 'Contenido OCR único A',
            'confidence' => 91.5,
            'language' => 'spa',
            'metadata' => [
                'word_count' => 4,
                'document_type' => 'reporte',
                'entities' => ['emails' => []],
                'keywords' => ['contenido', 'unico', 'a'],
            ],
        ]);
        $mock->shouldReceive('processFile')->once()->with($secondDocument->file_path, 'spa')->andReturn([
            'success' => true,
            'extracted_text' => 'Contenido OCR único B',
            'confidence' => 88.1,
            'language' => 'spa',
            'metadata' => [
                'word_count' => 4,
                'document_type' => 'carta',
                'entities' => ['emails' => []],
                'keywords' => ['contenido', 'unico', 'b'],
            ],
        ]);
    });

    $exitCode = Artisan::call('documents:process-ocr', [
        '--limit' => 2,
        '--language' => 'spa',
    ]);

    expect($exitCode)->toBe(0);

    $firstDocument->refresh();
    $secondDocument->refresh();

    expect($firstDocument->content)->toBe('Contenido OCR único A')
        ->and($secondDocument->content)->toBe('Contenido OCR único B')
        ->and(data_get($firstDocument->metadata, 'ocr_result.extracted_text'))->toBe('Contenido OCR único A')
        ->and(data_get($secondDocument->metadata, 'ocr_result.extracted_text'))->toBe('Contenido OCR único B')
        ->and(data_get($firstDocument->metadata, 'ocr_processed'))->toBeTrue()
        ->and(data_get($secondDocument->metadata, 'ocr_processed'))->toBeTrue();
});

it('marks documents without file path as ocr error without calling the extractor', function () {
    $document = makeOcrDocument([
        'title' => 'OCR Sin Archivo',
        'file_path' => null,
        'content' => null,
    ]);

    mock(OCRService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isTesseractAvailable')->once()->andReturnFalse();
        $mock->shouldNotReceive('processFile');
    });

    $exitCode = Artisan::call('documents:process-ocr', [
        '--document-id' => $document->id,
        '--language' => 'spa',
    ]);

    expect($exitCode)->toBe(0);

    $document->refresh();

    expect($document->content)->toBeNull()
        ->and(data_get($document->metadata, 'ocr_processed'))->toBeTrue()
        ->and(data_get($document->metadata, 'ocr_error'))->toBe('El documento no tiene un archivo asociado para OCR.');
});

it('reprocesses documents with existing ocr when force is enabled', function () {
    Storage::fake('local');
    Queue::fake();

    $document = makeOcrDocument([
        'title' => 'OCR Reproceso',
        'file_path' => 'documents/company-a/reprocess.pdf',
        'content' => 'Contenido viejo',
        'metadata' => [
            'ocr_processed' => true,
            'ocr_result' => [
                'extracted_text' => 'Contenido viejo',
            ],
        ],
    ]);

    Storage::disk('local')->put($document->file_path, 'pdf-reprocess');

    mock(OCRService::class, function (MockInterface $mock) use ($document): void {
        $mock->shouldReceive('isTesseractAvailable')->once()->andReturnFalse();
        $mock->shouldReceive('processFile')->once()->with($document->file_path, 'spa')->andReturn([
            'success' => true,
            'extracted_text' => 'Contenido OCR actualizado',
            'confidence' => 96.7,
            'language' => 'spa',
            'metadata' => [
                'word_count' => 3,
                'document_type' => 'comunicado',
                'entities' => ['emails' => []],
                'keywords' => ['contenido', 'ocr', 'actualizado'],
            ],
        ]);
    });

    $exitCode = Artisan::call('documents:process-ocr', [
        '--company-id' => $document->company_id,
        '--limit' => 10,
        '--language' => 'spa',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $document->refresh();

    expect($document->content)->toBe('Contenido OCR actualizado')
        ->and(data_get($document->metadata, 'ocr_result.extracted_text'))->toBe('Contenido OCR actualizado')
        ->and(data_get($document->metadata, 'ocr_error'))->toBeNull();
});
