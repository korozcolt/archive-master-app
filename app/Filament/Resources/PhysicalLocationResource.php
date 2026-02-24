<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\PhysicalLocationResource\Pages;
use App\Filament\Resources\PhysicalLocationResource\RelationManagers;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhysicalLocationResource extends Resource
{
    protected static ?string $model = PhysicalLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Ubicaciones Físicas';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 9;

    public static function getModelLabel(): string
    {
        return 'ubicación física';
    }

    public static function getPluralModelLabel(): string
    {
        return 'ubicaciones físicas';
    }

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: [
            'admin',
            'branch_admin',
            'office_manager',
            'archive_manager',
            'receptionist',
            'regular_user',
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()->company_id)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('template_id')
                            ->label('Plantilla de estructura')
                            ->relationship('template', 'name', fn (Builder $query, Get $get) => $query->where('company_id', $get('company_id'))
                                ->where('is_active', true)
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if (! $state) {
                                    $set('structured_data', null);

                                    return;
                                }

                                $template = PhysicalLocationTemplate::find($state);
                                if (! $template || ! is_array($template->levels)) {
                                    $set('structured_data', null);

                                    return;
                                }

                                $set('structured_data', static::buildStructuredDataDefaultsFromTemplate($template));
                            })
                            ->helperText('Selecciona la plantilla que define la estructura de esta ubicación'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Ubicación')
                    ->description('Define la ubicación física siguiendo la estructura de la plantilla')
                    ->schema([
                        Forms\Components\KeyValue::make('structured_data')
                            ->label('Datos de ubicación')
                            ->keyLabel('Nivel')
                            ->valueLabel('Valor')
                            ->reorderable(false)
                            ->formatStateUsing(fn (mixed $state): array => static::normalizeStructuredDataForKeyValue($state))
                            ->dehydrateStateUsing(fn (mixed $state): array => static::normalizeStructuredDataForKeyValue($state))
                            ->visible(fn (Get $get) => $get('template_id') !== null)
                            ->helperText('Define cada nivel de la ubicación según la plantilla'),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se genera automáticamente'),
                        Forms\Components\TextInput::make('full_path')
                            ->label('Ubicación completa')
                            ->maxLength(500)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se genera automáticamente'),
                    ]),

                Forms\Components\Section::make('Capacidad y Notas')
                    ->schema([
                        Forms\Components\TextInput::make('capacity_total')
                            ->label('Capacidad total')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Número máximo de documentos que puede almacenar'),
                        Forms\Components\TextInput::make('capacity_used')
                            ->label('Capacidad usada')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se actualiza automáticamente'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->placeholder('Información adicional sobre esta ubicación'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function normalizeStructuredDataForKeyValue(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $normalized = [];

        foreach ($state as $key => $value) {
            $normalizedKey = trim((string) $key);

            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($value)) {
                $normalizedValue = implode(', ', array_values(array_filter(array_map(
                    fn (mixed $item): string => trim((string) $item),
                    $value
                ), fn (string $item): bool => $item !== '')));
            } elseif (is_null($value)) {
                $normalizedValue = '';
            } else {
                $normalizedValue = trim((string) $value);
            }

            $normalized[$normalizedKey] = $normalizedValue;
        }

        return $normalized;
    }

    public static function buildStructuredDataDefaultsFromTemplate(PhysicalLocationTemplate $template): array
    {
        if (! is_array($template->levels)) {
            return [];
        }

        $defaults = [];

        foreach ($template->getOrderedLevels() as $level) {
            $label = trim((string) ($level['name'] ?? ''));

            if ($label === '') {
                continue;
            }

            $key = Str::lower($label);
            $defaults[$key] = '';
        }

        return $defaults;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('full_path')
                    ->label('Ubicación')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->full_path),
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Plantilla')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacidad')
                    ->formatStateUsing(fn ($record) => $record->capacity_total
                            ? "{$record->capacity_used}/{$record->capacity_total} ({$record->getCapacityPercentage()}%)"
                            : 'Sin límite'
                    )
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        ! $record->capacity_total => 'gray',
                        $record->getCapacityPercentage() >= 90 => 'danger',
                        $record->getCapacityPercentage() >= 70 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Creada por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('template')
                    ->label('Plantilla')
                    ->relationship('template', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default()
                    ->toggle(),
                Tables\Filters\Filter::make('full')
                    ->label('Llenas')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('capacity_used >= capacity_total')->whereNotNull('capacity_total'))
                    ->toggle(),
                Tables\Filters\Filter::make('available')
                    ->label('Disponibles')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('capacity_used < capacity_total')->orWhereNull('capacity_total'))
                    ->toggle(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver'),
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\Action::make('generateSticker')
                    ->label('Generar Etiqueta')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->url(fn (PhysicalLocation $record): string => route('stickers.locations.download', ['location' => $record->id])
                    )
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
                Tables\Actions\RestoreAction::make()->label('Restaurar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records): void {
                            $records->each->update(['is_active' => true]);
                            Notification::make()
                                ->title('Ubicaciones activadas')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records): void {
                            $records->each->update(['is_active' => false]);
                            Notification::make()
                                ->title('Ubicaciones desactivadas')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
                    Tables\Actions\RestoreBulkAction::make()->label('Restaurar seleccionadas'),
                    Tables\Actions\ForceDeleteBulkAction::make()->label('Eliminar permanentemente'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhysicalLocations::route('/'),
            'create' => Pages\CreatePhysicalLocation::route('/create'),
            'edit' => Pages\EditPhysicalLocation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Filtrar por empresa si no es super admin
        if (! Auth::user()->hasRole('super_admin')) {
            $query->where('company_id', Auth::user()->company_id);
        }

        return $query;
    }
}
