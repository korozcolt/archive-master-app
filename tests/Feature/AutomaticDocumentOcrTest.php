<?php

use App\Jobs\ProcessDocumentOcr;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Services\OCRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function makeAutomaticOcrDocument(array $overrides = []): Document
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

it('queues automatic ocr when a document is created with a file path', function () {
    Queue::fake();

    $document = makeAutomaticOcrDocument([
        'file_path' => 'documents/ocr/created.pdf',
    ]);

    Queue::assertPushed(ProcessDocumentOcr::class, function (ProcessDocumentOcr $job) use ($document): bool {
        return $job->documentId === $document->id && $job->force === false;
    });
});

it('queues automatic ocr only when file path changes on update', function () {
    Queue::fake();

    $document = makeAutomaticOcrDocument([
        'file_path' => null,
    ]);

    Queue::assertNotPushed(ProcessDocumentOcr::class);

    $document->update([
        'title' => 'Documento sin cambio de archivo',
    ]);

    Queue::assertNotPushed(ProcessDocumentOcr::class);

    $document->update([
        'file_path' => 'documents/ocr/updated.pdf',
    ]);

    Queue::assertPushed(ProcessDocumentOcr::class, function (ProcessDocumentOcr $job) use ($document): bool {
        return $job->documentId === $document->id && $job->force === true;
    });
});

it('processes automatic ocr job and stores extracted content for the document', function () {
    Storage::fake('local');

    $document = makeAutomaticOcrDocument([
        'file_path' => 'documents/ocr/job.pdf',
        'content' => null,
    ]);

    Storage::disk('local')->put($document->file_path, 'archivo-ocr');

    $ocrService = mock(OCRService::class, function (MockInterface $mock) use ($document): void {
        $mock->shouldReceive('processFile')
            ->once()
            ->with($document->file_path, 'spa')
            ->andReturn([
                'success' => true,
                'extracted_text' => 'Resumen OCR automático del documento.',
                'confidence' => 94.4,
                'language' => 'spa',
                'metadata' => [
                    'word_count' => 5,
                    'document_type' => 'comunicado',
                    'entities' => ['emails' => []],
                    'keywords' => ['resumen', 'ocr', 'automatico'],
                ],
            ]);
    });

    $job = app(ProcessDocumentOcr::class, [
        'documentId' => $document->id,
        'force' => false,
        'language' => 'spa',
    ]);

    $job->handle($ocrService);

    $document->refresh();

    expect($document->content)->toBe('Resumen OCR automático del documento.')
        ->and(data_get($document->metadata, 'ocr_processed'))->toBeTrue()
        ->and(data_get($document->metadata, 'ocr_result.word_count'))->toBe(5)
        ->and(data_get($document->metadata, 'ocr_error'))->toBeNull();
});
