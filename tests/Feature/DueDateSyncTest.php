<?php

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('syncs due_date into due_at', function () {
    $dueDate = now()->addDays(5)->startOfMinute();

    $document = Document::factory()->create([
        'due_date' => $dueDate,
    ])->refresh();

    expect($document->due_at?->toDateTimeString())->toBe($dueDate->toDateTimeString());
    expect($document->due_date?->toDateTimeString())->toBe($dueDate->toDateTimeString());
});

it('syncs due_at into due_date', function () {
    $dueAt = now()->addDays(3)->startOfMinute();

    $document = Document::factory()->create([
        'due_at' => $dueAt,
    ])->refresh();

    expect($document->due_at?->toDateTimeString())->toBe($dueAt->toDateTimeString());
    expect($document->due_date?->toDateTimeString())->toBe($dueAt->toDateTimeString());
});
