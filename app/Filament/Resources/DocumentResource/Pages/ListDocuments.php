<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Rápido'),
            Actions\Action::make('createWizard')
                ->label('Crear con Asistente')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->url(fn (): string => DocumentResource::getUrl('create-wizard'))
                ->tooltip('Crear documento paso a paso con asistente'),
            Actions\Action::make('myDocuments')
                ->label('Mis Documentos')
                ->icon('heroicon-o-user')
                ->action(function () {
                    $this->tableFilters['assigned_to']['value'] = Auth::id();
                    $this->resetTableSearch();
                    $this->resetTableColumnSearches();
                }),
            Actions\Action::make('pendingDocuments')
                ->label('Pendientes')
                ->icon('heroicon-o-clock')
                ->action(function () {
                    $this->tableFilters['overdue']['isActive'] = true;
                    $this->resetTableSearch();
                    $this->resetTableColumnSearches();
                }),
            Actions\Action::make('todayDue')
                ->label('Vencen Hoy')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning')
                ->action(function () {
                    $this->tableFilters['due_today']['isActive'] = true;
                    $this->resetTableSearch();
                    $this->resetTableColumnSearches();
                }),
            Actions\Action::make('importDocuments')
                ->label('Importar')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Archivo CSV')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                        ->required(),
                    \Filament\Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    \Filament\Forms\Components\Select::make('branch_id')
                        ->label('Sucursal')
                        ->relationship('branch', 'name', fn (Builder $query, \Filament\Forms\Get $get) =>
                            $query->where('company_id', $get('company_id'))
                        )
                        ->searchable()
                        ->preload(),
                    \Filament\Forms\Components\Toggle::make('header_row')
                        ->label('Primera fila contiene encabezados')
                        ->default(true),
                    \Filament\Forms\Components\Toggle::make('create_categories')
                        ->label('Crear categorías automáticamente si no existen')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    // Esta es solo una simulación - en un sistema real haría la importación
                    // TODO: Implementar lógica real de importación CSV en el futuro

                    Notification::make()
                        ->title('Importación iniciada')
                        ->body('La importación de documentos se ha iniciado. Este proceso puede tomar varios minutos.')
                        ->success()
                        ->send();
                })
                ->visible(fn() => Auth::user()->hasRole(['admin', 'super_admin', 'branch_admin'])),
        ];
    }

    protected function getTableQuery(): Builder
    {
        // Base query from resource
        $query = static::$resource::getEloquentQuery();

        // Apply any additional constraints
        $user = Auth::user();

        // If not super admin, limit to documents from their company
        if ($user && !$user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);

            // If branch admin, limit to their branch
            if ($user->hasRole('branch_admin') && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            // If office manager, limit to their department
            if ($user->hasRole('office_manager') && $user->department_id) {
                $query->where('department_id', $user->department_id);
            }

            // If regular user, only show assigned documents or created by them
            if ($user->hasRole('regular_user')) {
                $query->where(function($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                });
            }
        }

        return $query;
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'created_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }
}
