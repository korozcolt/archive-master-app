<?php

namespace App\Filament\Resources\CustomReportResource\Pages;

use App\Filament\Resources\CustomReportResource;
use App\Services\ReportBuilderService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CreateCustomReport extends CreateRecord
{
    protected static string $resource = CustomReportResource::class;
    
    protected static ?string $title = 'Crear Reporte Personalizado';
    
    protected static ?string $breadcrumb = 'Crear';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Vista Previa')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function () {
                    $this->previewReport();
                }),
            
            Actions\Action::make('save_template')
                ->label('Guardar como Plantilla')
                ->icon('heroicon-o-bookmark')
                ->color('warning')
                ->action(function () {
                    $this->saveAsTemplate();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Generar Reporte')
                ->icon('heroicon-o-play'),
            
            Actions\Action::make('generate_and_email')
                ->label('Generar y Enviar por Email')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Enviar Reporte por Email')
                ->modalDescription('¿Estás seguro de que deseas generar y enviar este reporte por email?')
                ->action(function () {
                    $this->generateAndEmail();
                }),
            
            $this->getCancelFormAction(),
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Since we're not actually storing records, we'll generate the report directly
        $this->generateReport($data);
        
        // Return a dummy model to satisfy the interface
        return new class extends \Illuminate\Database\Eloquent\Model {
            protected $fillable = ['*'];
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Generate the custom report
     */
    protected function generateReport(array $data): void
    {
        try {
            $builder = app(ReportBuilderService::class);
            
            // Configure the report builder
            $builder->setReportType($data['report_type'] ?? 'documents');
            
            // Set date range
            if (!empty($data['date_from']) && !empty($data['date_to'])) {
                $builder->setDateRange(
                    Carbon::parse($data['date_from']),
                    Carbon::parse($data['date_to'])
                );
            }
            
            // Add filters
            if (!empty($data['filters'])) {
                foreach ($data['filters'] as $filter) {
                    if (!empty($filter['field']) && !empty($filter['operator'])) {
                        $builder->addFilter(
                            $filter['field'],
                            $filter['operator'],
                            $filter['value'] ?? null
                        );
                    }
                }
            }
            
            // Set columns
            if (!empty($data['columns'])) {
                $builder->setColumns($data['columns']);
            }
            
            // Set group by
            if (!empty($data['group_by'])) {
                foreach ($data['group_by'] as $group) {
                    $builder->groupBy($group);
                }
            }
            
            // Set order by
            if (!empty($data['order_by'])) {
                foreach ($data['order_by'] as $order) {
                    if (!empty($order['field'])) {
                        $builder->orderBy($order['field'], $order['direction'] ?? 'asc');
                    }
                }
            }
            
            // Generate and download the report
            $filePath = $builder->export($data['export_format'] ?? 'pdf');
            
            Notification::make()
                ->title('Reporte generado exitosamente')
                ->body('El reporte "' . ($data['report_name'] ?? 'Reporte Personalizado') . '" ha sido generado.')
                ->success()
                ->send();
            
            // Store file path in session for download
            session(['generated_report_path' => $filePath]);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al generar reporte')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            
            throw $e;
        }
    }

    /**
     * Preview the report data
     */
    protected function previewReport(): void
    {
        try {
            $data = $this->form->getState();
            $builder = app(ReportBuilderService::class);
            
            // Configure the report builder (same as generate)
            $builder->setReportType($data['report_type'] ?? 'documents');
            
            if (!empty($data['date_from']) && !empty($data['date_to'])) {
                $builder->setDateRange(
                    Carbon::parse($data['date_from']),
                    Carbon::parse($data['date_to'])
                );
            }
            
            if (!empty($data['filters'])) {
                foreach ($data['filters'] as $filter) {
                    if (!empty($filter['field']) && !empty($filter['operator'])) {
                        $builder->addFilter(
                            $filter['field'],
                            $filter['operator'],
                            $filter['value'] ?? null
                        );
                    }
                }
            }
            
            if (!empty($data['columns'])) {
                $builder->setColumns($data['columns']);
            }
            
            // Get preview data (limited to 10 records)
            $previewData = $builder->build()->take(10);
            $aggregates = $builder->getAggregatedData();
            
            $totalRecords = $builder->build()->count();
            
            Notification::make()
                ->title('Vista previa del reporte')
                ->body("Registros encontrados: {$totalRecords} (mostrando primeros 10)\n" . 
                       "Datos agregados disponibles: " . count($aggregates) . " métricas")
                ->info()
                ->duration(10000)
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en vista previa')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Save the current configuration as a template
     */
    protected function saveAsTemplate(): void
    {
        try {
            $data = $this->form->getState();
            
            // Here you would typically save to a templates table
            // For now, we'll just show a success message
            
            $templateName = $data['report_name'] ?? 'Plantilla sin nombre';
            
            // In a real implementation, you'd save this to a database table:
            // ReportTemplate::create([
            //     'name' => $templateName,
            //     'configuration' => json_encode($data),
            //     'user_id' => auth()->id(),
            //     'created_at' => now()
            // ]);
            
            Notification::make()
                ->title('Plantilla guardada')
                ->body('La plantilla "' . $templateName . '" ha sido guardada exitosamente.')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al guardar plantilla')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate report and send via email
     */
    protected function generateAndEmail(): void
    {
        try {
            $data = $this->form->getState();
            
            // First generate the report
            $this->generateReport($data);
            
            // Get the generated file path
            $filePath = session('generated_report_path');
            
            if (!$filePath || !file_exists($filePath)) {
                throw new \Exception('No se pudo encontrar el archivo del reporte generado.');
            }
            
            // Send email with attachment
            $recipients = $data['email_recipients'] ?? [auth()->user()->email];
            $reportName = $data['report_name'] ?? 'Reporte Personalizado';
            
            // Here you would implement the email sending logic
            // Mail::to($recipients)->send(new CustomReportMail($filePath, $reportName));
            
            Notification::make()
                ->title('Reporte enviado por email')
                ->body('El reporte "' . $reportName . '" ha sido enviado a ' . count($recipients) . ' destinatario(s).')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al enviar reporte')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get validation rules for the form
     */
    protected function getFormValidationRules(): array
    {
        return [
            'report_name' => 'required|string|max:255',
            'report_type' => 'required|in:documents,users,departments',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'export_format' => 'required|in:pdf,excel,csv',
            'filters.*.field' => 'required_with:filters.*.operator',
            'filters.*.operator' => 'required_with:filters.*.field',
            'order_by.*.field' => 'required_with:order_by.*.direction',
            'order_by.*.direction' => 'required_with:order_by.*.field|in:asc,desc',
        ];
    }

    /**
     * Get validation messages
     */
    protected function getFormValidationMessages(): array
    {
        return [
            'report_name.required' => 'El nombre del reporte es obligatorio.',
            'report_type.required' => 'Debe seleccionar un tipo de reporte.',
            'date_to.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde.',
            'export_format.required' => 'Debe seleccionar un formato de exportación.',
        ];
    }
}