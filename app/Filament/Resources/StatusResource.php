<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\StatusResource\Pages;
use App\Filament\Resources\StatusResource\RelationManagers;
use App\Models\Status;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class StatusResource extends Resource
{
    use Translatable;

    protected static ?string $model = Status::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Estados';

    protected static ?string $navigationGroup = 'Gestión Documental';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(
            roles: ['admin', 'archive_manager'],
            permissions: ['manage-categories']
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Estado')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Apariencia')
                    ->schema([
                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),
                        Forms\Components\TextInput::make('icon')
                            ->label('Icono')
                            ->maxLength(255)
                            ->helperText('Clases de iconos (ej: heroicon-o-check-circle)'),
                        Forms\Components\TextInput::make('order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Comportamiento')
                    ->schema([
                        Forms\Components\Toggle::make('is_initial')
                            ->label('Es estado inicial')
                            ->helperText('Este estado se asigna cuando se crea un documento'),
                        Forms\Components\Toggle::make('is_final')
                            ->label('Es estado final')
                            ->helperText('Este estado marca el fin del flujo de trabajo'),
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Detalles')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535),
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
                            ->rows(3)
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if (is_array($state)) {
                            return $record->company->getTranslation('name', app()->getLocale());
                        }

                        return $state;
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if (is_array($state)) {
                            return $record->getTranslation('name', app()->getLocale());
                        }

                        return $state;
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\IconColumn::make('is_initial')
                    ->label('Estado Inicial')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_final')
                    ->label('Estado Final')
                    ->boolean(),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_workflows_count')
                    ->label('Transiciones salientes')
                    ->counts('fromWorkflows')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_initial')
                    ->label('Estados iniciales')
                    ->query(fn (Builder $query): Builder => $query->where('is_initial', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_final')
                    ->label('Estados finales')
                    ->query(fn (Builder $query): Builder => $query->where('is_final', true))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('activateStatuses')
                        ->label('Activar Estados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    Tables\Actions\BulkAction::make('deactivateStatuses')
                        ->label('Desactivar Estados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\FromWorkflowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatuses::route('/'),
            'create' => Pages\CreateStatus::route('/create'),
            'view' => Pages\ViewStatus::route('/{record}'),
            'edit' => Pages\EditStatus::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
