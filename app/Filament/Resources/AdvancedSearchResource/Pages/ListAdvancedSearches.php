<?php

namespace App\Filament\Resources\AdvancedSearchResource\Pages;

use App\Filament\Resources\AdvancedSearchResource;
use App\Filament\Widgets\AdvancedSearchStatsWidget;
use App\Models\Document;
use App\Models\User;
use App\Models\Company;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;

class ListAdvancedSearches extends ListRecords
{
    protected static string $resource = AdvancedSearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('advanced_search')
                ->label('Búsqueda Avanzada')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->form([
                    Section::make('Criterios de Búsqueda')
                        ->schema([
                            TextInput::make('search_query')
                                ->label('Buscar en contenido')
                                ->placeholder('Ingrese términos de búsqueda...')
                                ->helperText('Busca en título, descripción y contenido usando Meilisearch')
                                ->columnSpanFull(),
                                
                            Grid::make(2)
                                ->schema([
                                    Select::make('company_id')
                                        ->label('Empresa')
                                        ->options(Company::pluck('name', 'id'))
                                        ->searchable()
                                        ->multiple(),
                                        
                                    Select::make('created_by')
                                        ->label('Creado por')
                                        ->options(User::pluck('name', 'id'))
                                        ->searchable()
                                        ->multiple(),
                                ]),
                                
                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('created_from')
                                        ->label('Creado desde')
                                        ->native(false),
                                        
                                    DatePicker::make('created_to')
                                        ->label('Creado hasta')
                                        ->native(false),
                                ]),
                                
                            Grid::make(3)
                                ->schema([
                                    Toggle::make('is_overdue')
                                        ->label('Solo documentos vencidos'),
                                        
                                    Toggle::make('recent_activity')
                                        ->label('Actividad reciente (7 días)'),
                                        
                                    Toggle::make('save_search')
                                        ->label('Guardar esta búsqueda'),
                                ]),
                        ]),
                ])
                ->action(function (array $data) {
                    // Guardar criterios de búsqueda en sesión
                    Session::put('advanced_search', $data);
                    
                    if ($data['save_search'] ?? false) {
                        Notification::make()
                            ->title('Búsqueda guardada')
                            ->body('Los criterios de búsqueda han sido guardados en tu sesión.')
                            ->success()
                            ->send();
                    }
                    
                    // Recargar la página para aplicar filtros
                    return redirect()->to(request()->url());
                })
                ->modalSubmitActionLabel('Buscar')
                ->modalCancelActionLabel('Cancelar'),
                
            Actions\Action::make('clear_search')
                ->label('Limpiar Búsqueda')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(function () {
                    Session::forget('advanced_search');
                    
                    Notification::make()
                        ->title('Búsqueda limpiada')
                        ->body('Se han eliminado todos los criterios de búsqueda.')
                        ->success()
                        ->send();
                        
                    return redirect()->to(request()->url());
                })
                ->visible(fn () => Session::has('advanced_search')),
                
            Actions\Action::make('search_help')
                ->label('Ayuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ayuda de Búsqueda Avanzada')
                ->modalDescription(
                    'Esta herramienta utiliza Meilisearch para búsquedas rápidas y precisas. ' .
                    'Puedes buscar por contenido, filtrar por empresa, usuario, fechas y más. ' .
                    'Los resultados se actualizan automáticamente y puedes guardar tus búsquedas frecuentes.'
                )
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        $query = Document::query()->with(['category', 'creator', 'company', 'tags']);
        
        // Obtener criterios de búsqueda de la sesión
        $searchCriteria = Session::get('advanced_search', []);
        
        // Aplicar búsqueda full-text con Scout
        if (!empty($searchCriteria['search_query'])) {
            $searchResults = Document::search($searchCriteria['search_query'])->get();
            $documentIds = $searchResults->pluck('id')->toArray();
            
            if (!empty($documentIds)) {
                $query->whereIn('id', $documentIds);
            } else {
                // Fallback a búsqueda tradicional si Scout no devuelve resultados
                $query->where(function ($q) use ($searchCriteria) {
                    $searchTerm = $searchCriteria['search_query'];
                    $q->where('title', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('content', 'like', "%{$searchTerm}%");
                });
            }
        }
        
        // Aplicar filtros adicionales
        if (!empty($searchCriteria['company_id'])) {
            $query->whereIn('company_id', (array) $searchCriteria['company_id']);
        }
        
        if (!empty($searchCriteria['created_by'])) {
            $query->whereIn('created_by', (array) $searchCriteria['created_by']);
        }
        
        if (!empty($searchCriteria['created_from'])) {
            $query->whereDate('created_at', '>=', $searchCriteria['created_from']);
        }
        
        if (!empty($searchCriteria['created_to'])) {
            $query->whereDate('created_at', '<=', $searchCriteria['created_to']);
        }
        
        if (!empty($searchCriteria['is_overdue'])) {
            $query->where('due_date', '<', now());
        }
        
        if (!empty($searchCriteria['recent_activity'])) {
            $query->where('updated_at', '>=', now()->subDays(7));
        }
        
        // También aplicar búsqueda de tabla si existe
        if ($tableSearch = request('tableSearch')) {
            $searchResults = Document::search($tableSearch)->get();
            $documentIds = $searchResults->pluck('id')->toArray();
            
            if (!empty($documentIds)) {
                $query->whereIn('id', $documentIds);
            } else {
                $query->where(function ($q) use ($tableSearch) {
                    $q->where('title', 'like', "%{$tableSearch}%")
                      ->orWhere('description', 'like', "%{$tableSearch}%")
                      ->orWhere('document_number', 'like', "%{$tableSearch}%");
                });
            }
        }
        
        return $query;
    }
    
    public function getTitle(): string
    {
        $searchCriteria = Session::get('advanced_search', []);
        $hasActiveSearch = !empty($searchCriteria);
        
        return $hasActiveSearch 
            ? 'Búsqueda Avanzada - Filtros Activos' 
            : 'Búsqueda Avanzada';
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            AdvancedSearchStatsWidget::class,
        ];
    }
}
