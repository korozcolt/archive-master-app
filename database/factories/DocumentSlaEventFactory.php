<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentSlaEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSlaEvent>
 */
class DocumentSlaEventFactory extends Factory
{
    protected $model = DocumentSlaEvent::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'company_id' => Company::factory(),
            'event_type' => 'sla_started',
            'status_before' => null,
            'status_after' => 'running',
            'occurred_at' => now(),
            'metadata' => [],
        ];
    }
}
