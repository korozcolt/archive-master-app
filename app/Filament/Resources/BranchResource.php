<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    use Translatable;

    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Sucursales';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(permissions: [
            'manage-branches',
            'manage-branch',
        ]);
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
                Forms\Components\Section::make('Información básica')
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
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('city')
                                    ->label('Ciudad')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('state')
                                    ->label('Estado/Provincia')
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('country')
                                    ->label('País')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('postal_code')
                                    ->label('Código postal')
                                    ->maxLength(255),
                            ]),
                    ]),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255),
                            ]),
                    ]),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Activa')
                            ->default(true),
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('departments_count')
                    ->label('Departamentos')
                    ->counts('departments')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada el')
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
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
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
                    Tables\Actions\BulkAction::make('activateBranches')
                        ->label('Activar Sucursales')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    Tables\Actions\BulkAction::make('deactivateBranches')
                        ->label('Desactivar Sucursales')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DepartmentsRelationManager::class,
            RelationManagers\UsersRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
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
