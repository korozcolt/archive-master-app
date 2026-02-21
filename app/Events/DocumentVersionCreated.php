<?php

namespace App\Events;

use App\Models\DocumentVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentVersionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public DocumentVersion $documentVersion) {}
}
