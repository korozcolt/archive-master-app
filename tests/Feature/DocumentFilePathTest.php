<?php

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists file_path on document create', function () {
    $document = Document::factory()->create([
        'file_path' => 'documents/test-file.png',
    ]);

    expect($document->file_path)->toBe('documents/test-file.png');
    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'file_path' => 'documents/test-file.png',
    ]);
});
