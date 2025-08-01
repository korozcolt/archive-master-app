<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Services\WorkflowEngine;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class OverdueDocumentsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected static ?string $heading = 'Documentos Vencidos';
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? 1;
        
        $workflowEngine = new WorkflowEngine();
        $overdueDocuments = collect($workflowEngine->getOverdueDocuments())
            ->filter(function ($item) use ($companyId) {
                return $item['document']->company_id === $companyId;
            })
            ->pluck('document')
            ->pluck('id')
            ->toArray();
        
        return $table
            ->query(
                Document::query()
                    ->whereIn('id', $overdueDocuments)
                    ->with(['company', 'category', 'status', 'assignee'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignado')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Días Vencido')
                    ->getStateUsing(function (Document $record) {
                        $workflowEngine = new WorkflowEngine();
                        $overdueData = collect($workflowEngine->getOverdueDocuments())
                            ->firstWhere('document.id', $record->id);
                        
                        if ($overdueData) {
                            return round($overdueData['hours_overdue'] / 24, 1);
                        }
                        
                        return 0;
                    })
                    ->badge()
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Document $record): string => route('filament.admin.resources.documents.view', $record))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-m-pencil')
                    ->url(fn (Document $record): string => route('filament.admin.resources.documents.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('¡Excelente!')
            ->emptyStateDescription('No hay documentos vencidos en este momento.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}