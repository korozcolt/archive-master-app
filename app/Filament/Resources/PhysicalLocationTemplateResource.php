<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PhysicalLocationTemplateResource\Pages;
use App\Filament\Resources\PhysicalLocationTemplateResource\RelationManagers;
use App\Models\PhysicalLocationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PhysicalLocationTemplateResource extends Resource
{
    protected static ?string $model = PhysicalLocationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';

    protected static ?string $navigationLabel = 'Plantillas de Ubicación';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 8;

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
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la plantilla')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Estructura de Archivo Principal'),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535)
                            ->placeholder('Describe el uso de esta plantilla'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->required()
                            ->helperText('Solo las plantillas activas pueden ser usadas'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Estructura de Niveles')
                    ->description('Define los niveles jerárquicos de ubicación (Ej: Edificio > Piso > Sala > Armario > Estante > Caja)')
                    ->schema([
                        Forms\Components\Repeater::make('levels')
                            ->label('Niveles')
                            ->schema([
                                Forms\Components\TextInput::make('order')
                                    ->label('Orden')
                                    ->numeric()
                                    ->required()
                                    ->default(fn (Get $get) => count($get('../../levels') ?? []) + 1)
                                    ->minValue(1)
                                    ->helperText('Orden jerárquico del nivel'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del nivel')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Ej: Edificio, Piso, Sala, Armario')
                                    ->helperText('Nombre descriptivo del nivel'),
                                Forms\Components\TextInput::make('code')
                                    ->label('Código corto')
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('Ej: ED, P, SALA, ARM')
                                    ->helperText('Código para generar el identificador único'),
                                Forms\Components\Toggle::make('required')
                                    ->label('Requerido')
                                    ->default(true)
                                    ->helperText('Si este nivel es obligatorio'),
                                Forms\Components\TextInput::make('icon')
                                    ->label('Icono (opcional)')
                                    ->maxLength(50)
                                    ->placeholder('heroicon-o-building')
                                    ->helperText('Icono de Heroicons'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel('Agregar Nivel')
                            ->required()
                            ->minItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('levels')
                    ->label('Niveles')
                    ->formatStateUsing(fn ($state) => count($state ?? []))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Ubicaciones creadas')
                    ->counts('locations')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
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
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default()
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->action(function (PhysicalLocationTemplate $record): void {
                        $newTemplate = $record->replicate();
                        $newTemplate->name = $record->name . ' (Copia)';
                        $newTemplate->is_active = false;
                        $newTemplate->created_by = Auth::id();
                        $newTemplate->save();

                        Notification::make()
                            ->title('Plantilla duplicada')
                            ->body('La plantilla ha sido duplicada correctamente.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
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
                                ->title('Plantillas activadas')
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
                                ->title('Plantillas desactivadas')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\LocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhysicalLocationTemplates::route('/'),
            'create' => Pages\CreatePhysicalLocationTemplate::route('/create'),
            // 'view' => Pages\ViewPhysicalLocationTemplate::route('/{record}'),
            'edit' => Pages\EditPhysicalLocationTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Filtrar por empresa si no es super admin
        if (!Auth::user()->hasRole('super_admin')) {
            $query->where('company_id', Auth::user()->company_id);
        }

        return $query;
    }
}
