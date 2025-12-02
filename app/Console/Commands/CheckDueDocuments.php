<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Notifications\DocumentDueSoon;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckDueDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:check-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica documentos próximos a vencer y envía notificaciones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verificando documentos próximos a vencer...');

        // Obtener fechas de referencia
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $threeDays = Carbon::today()->addDays(3);
        $sevenDays = Carbon::today()->addDays(7);

        // Documentos que vencen hoy
        $dueTodayCount = $this->notifyDocuments(
            Document::whereNotNull('due_at')
                ->whereDate('due_at', $today)
                ->whereNotNull('assigned_to')
                ->with('assignedTo')
                ->get(),
            0
        );

        // Documentos que vencen mañana
        $dueTomorrowCount = $this->notifyDocuments(
            Document::whereNotNull('due_at')
                ->whereDate('due_at', $tomorrow)
                ->whereNotNull('assigned_to')
                ->with('assignedTo')
                ->get(),
            1
        );

        // Documentos que vencen en 3 días
        $dueThreeDaysCount = $this->notifyDocuments(
            Document::whereNotNull('due_at')
                ->whereDate('due_at', $threeDays)
                ->whereNotNull('assigned_to')
                ->with('assignedTo')
                ->get(),
            3
        );

        // Documentos que vencen en 7 días
        $dueSevenDaysCount = $this->notifyDocuments(
            Document::whereNotNull('due_at')
                ->whereDate('due_at', $sevenDays)
                ->whereNotNull('assigned_to')
                ->with('assignedTo')
                ->get(),
            7
        );

        $totalNotifications = $dueTodayCount + $dueTomorrowCount + $dueThreeDaysCount + $dueSevenDaysCount;

        $this->info("✅ Proceso completado:");
        $this->line("   - Vencen hoy: {$dueTodayCount} notificaciones");
        $this->line("   - Vencen mañana: {$dueTomorrowCount} notificaciones");
        $this->line("   - Vencen en 3 días: {$dueThreeDaysCount} notificaciones");
        $this->line("   - Vencen en 7 días: {$dueSevenDaysCount} notificaciones");
        $this->line("   - Total: {$totalNotifications} notificaciones enviadas");

        return Command::SUCCESS;
    }

    /**
     * Envía notificaciones para una colección de documentos
     */
    private function notifyDocuments($documents, int $daysRemaining): int
    {
        $count = 0;

        foreach ($documents as $document) {
            if ($document->assignedTo) {
                // Verificar si ya se envió notificación hoy para este documento
                $alreadyNotified = $document->assignedTo
                    ->notifications()
                    ->whereDate('created_at', Carbon::today())
                    ->where('type', DocumentDueSoon::class)
                    ->where('data->document_id', $document->id)
                    ->exists();

                if (!$alreadyNotified) {
                    $document->assignedTo->notify(new DocumentDueSoon($document, $daysRemaining));
                    $count++;
                }
            }
        }

        return $count;
    }
}
