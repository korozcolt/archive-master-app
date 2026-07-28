<?php

namespace App\Console\Commands;

use App\Models\DocumentAccessRequest;
use Illuminate\Console\Command;

class ExpireDocumentAccessRequests extends Command
{
    protected $signature = 'access-requests:expire';

    protected $description = 'Marca como expiradas las solicitudes de acceso a documentos aprobadas cuyo plazo ya venció';

    public function handle(): int
    {
        $expired = DocumentAccessRequest::query()
            ->where('status', 'approved')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Solicitudes de acceso marcadas como expiradas: {$expired}");

        return self::SUCCESS;
    }
}
