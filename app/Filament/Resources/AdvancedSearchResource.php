<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvancedSearchResource\Pages;
use App\Models\Document;
use App\Models\Category;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use App\Models\Tag;
use App\Enums\Priority;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class AdvancedSearchResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Búsqueda Avanzada';

    protected static ?string $modelLabel = 'Búsqueda Avanzada';

    protected static ?string $pluralModelLabel = 'Búsqueda Avanzada';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Documentos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Criterios de Búsqueda')
                    ->schema([
                        Forms\Components\TextInput::make('search_query')
                            ->label('Buscar en contenido')
                            ->placeholder('Ingrese términos de búsqueda...')
                            ->helperText('Busca en título, descripción y contenido de documentos usando Meilisearch')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                    ->label('Tipo de Documento')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->multiple(),

                                Forms\Components\Select::make('status_id')
                                    ->label('Estado')
                                    ->relationship('status', 'name')
                                    ->searchable()
                                    ->multiple(),

                                Forms\Components\Select::make('category_id')
                                    ->label('Categoría')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->multiple(),

                                Forms\Components\Select::make('tags')
                                    ->label('Etiquetas')
                                    ->relationship('tags', 'name')
                                    ->searchable()
                                    ->multiple(),

                                Forms\Components\Select::make('created_by')
                                    ->label('Creado por')
                                    ->relationship('creator', 'name')
                                    ->searchable()
                                    ->multiple(),

                                Forms\Components\Select::make('company_id')
                                    ->label('Empresa')
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->multiple(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('created_from')
                                    ->label('Creado desde')
                                    ->native(false),

                                Forms\Components\DatePicker::make('created_to')
                                    ->label('Creado hasta')
                                    ->native(false),

                                Forms\Components\DatePicker::make('due_from')
                                    ->label('Vence desde')
                                    ->native(false),

                                Forms\Components\DatePicker::make('due_to')
                                    ->label('Vence hasta')
                                    ->native(false),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('has_attachments')
                                    ->label('Con archivos adjuntos'),

                                Forms\Components\Toggle::make('is_overdue')
                                    ->label('Documentos vencidos'),

                                Forms\Components\Toggle::make('recent_activity')
                                    ->label('Actividad reciente (7 días)'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getSearchQuery())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (Document $record): ?string {
                        return $record->title;
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status.name')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Fecha límite')
                    ->date()
                    ->sortable()
                    ->color(fn (Document $record): string =>
                        $record->due_date && $record->due_date->isPast() ? 'danger' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Tipo de Documento')
                    ->relationship('category', 'name')
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->multiple(),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->multiple(),

                SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->multiple(),

                Filter::make('overdue')
                    ->label('Documentos vencidos')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('due_date', '<', now())
                    )
                    ->toggle(),

                Filter::make('recent')
                    ->label('Actividad reciente')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('updated_at', '>=', now()->subDays(7))
                    )
                    ->toggle(),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Creado desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Creado hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->url(fn (Document $record): string => DocumentResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvancedSearches::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    protected static function getSearchQuery(): Builder
    {
        $query = Document::query()->with(['category', 'creator', 'company', 'tags']);

        // Obtener parámetros de búsqueda de la URL o sesión
        $searchQuery = request('search_query') ?? session('advanced_search.search_query');

        if ($searchQuery) {
            // Usar Scout para búsqueda full-text si hay términos de búsqueda
            $searchResults = Document::search($searchQuery)->get();
            $documentIds = $searchResults->pluck('id')->toArray();

            if (!empty($documentIds)) {
                $query->whereIn('id', $documentIds);
            } else {
                // Si no hay resultados en Scout, usar búsqueda tradicional como fallback
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('title', 'like', "%{$searchQuery}%")
                      ->orWhere('description', 'like', "%{$searchQuery}%")
                      ->orWhere('content', 'like', "%{$searchQuery}%");
                });
            }
        }

        return $query;
    }
}
