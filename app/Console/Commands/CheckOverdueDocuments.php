<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\DocumentOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckOverdueDocuments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'documents:check-overdue 
                            {--company= : ID de la empresa específica}
                            {--notify : Enviar notificaciones}
                            {--dry-run : Ejecutar sin realizar cambios}';

    /**
     * The console command description.
     */
    protected $description = 'Verificar documentos con SLA vencido y enviar notificaciones';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Iniciando verificación de documentos vencidos...');
        
        $companyId = $this->option('company');
        $shouldNotify = $this->option('notify');
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('⚠️  Modo DRY RUN - No se realizarán cambios ni enviarán notificaciones');
        }
        
        // Obtener documentos potencialmente vencidos
        $documentsQuery = Document::whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', function ($query) {
                $query->where('is_final', false);
            })
            ->with(['status', 'assignee', 'company', 'category']);
            
        if ($companyId) {
            $documentsQuery->where('company_id', $companyId);
            $this->info("🏢 Filtrando por empresa ID: {$companyId}");
        }
        
        $overdueDocuments = $documentsQuery->get();
        
        if ($overdueDocuments->isEmpty()) {
            $this->info('✅ No se encontraron documentos vencidos');
            return self::SUCCESS;
        }
        
        $this->info("📋 Encontrados {$overdueDocuments->count()} documentos vencidos");
        
        $notificationsSent = 0;
        $documentsProcessed = 0;
        
        foreach ($overdueDocuments as $document) {
            $documentsProcessed++;
            
            $daysOverdue = now()->diffInDays($document->due_at, false);
            
            $this->line(sprintf(
                '📄 %s - %s (Vencido hace %d días)',
                $document->document_number,
                $document->title,
                abs($daysOverdue)
            ));
            
            // Verificar si tiene SLA definido
            $workflowDefinition = $this->getWorkflowDefinition($document);
            
            if ($workflowDefinition && $workflowDefinition->sla_hours) {
                $slaDeadline = $document->created_at->addHours($workflowDefinition->sla_hours);
                
                if (now()->gt($slaDeadline)) {
                    $this->warn("  ⚠️  SLA excedido (límite: {$slaDeadline->format('d/m/Y H:i')})");
                    
                    if (!$isDryRun) {
                        // Marcar como SLA excedido si no está marcado
                        if (!$document->sla_exceeded_at) {
                            $document->update([
                                'sla_exceeded_at' => now(),
                                'sla_exceeded_by' => now()->diffInHours($slaDeadline),
                            ]);
                            
                            Log::warning('SLA excedido detectado', [
                                'document_id' => $document->id,
                                'document_number' => $document->document_number,
                                'sla_hours' => $workflowDefinition->sla_hours,
                                'deadline' => $slaDeadline,
                                'exceeded_by_hours' => now()->diffInHours($slaDeadline),
                            ]);
                        }
                    }
                }
            }
            
            // Enviar notificaciones si está habilitado
            if ($shouldNotify && !$isDryRun) {
                $this->sendOverdueNotifications($document, abs($daysOverdue));
                $notificationsSent++;
            }
        }
        
        $this->newLine();
        $this->info("✅ Procesamiento completado:");
        $this->line("   📊 Documentos procesados: {$documentsProcessed}");
        
        if ($shouldNotify && !$isDryRun) {
            $this->line("   📧 Notificaciones enviadas: {$notificationsSent}");
        }
        
        if ($isDryRun) {
            $this->warn('⚠️  Recuerda: Este fue un DRY RUN - No se realizaron cambios');
        }
        
        return self::SUCCESS;
    }
    
    /**
     * Obtener la definición de workflow para un documento
     */
    private function getWorkflowDefinition(Document $document): ?WorkflowDefinition
    {
        return WorkflowDefinition::where('from_status_id', $document->status_id)
            ->where('company_id', $document->company_id)
            ->first();
    }
    
    /**
     * Enviar notificaciones de documento vencido
     */
    private function sendOverdueNotifications(Document $document, int $daysOverdue): void
    {
        $usersToNotify = collect();
        
        // Notificar al asignado
        if ($document->assignee) {
            $usersToNotify->push($document->assignee);
        }
        
        // Notificar al supervisor del departamento
        if ($document->department && $document->department->supervisor) {
            $usersToNotify->push($document->department->supervisor);
        }
        
        // Notificar a administradores de la empresa
        $companyAdmins = User::where('company_id', $document->company_id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->get();
            
        $usersToNotify = $usersToNotify->merge($companyAdmins)->unique('id');
        
        foreach ($usersToNotify as $user) {
            try {
                $user->notify(new DocumentOverdue($document, $daysOverdue));
                $this->line("  📧 Notificación enviada a: {$user->name}");
            } catch (\Exception $e) {
                $this->error("  ❌ Error enviando notificación a {$user->name}: {$e->getMessage()}");
                Log::error('Error enviando notificación de documento vencido', [
                    'user_id' => $user->id,
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}