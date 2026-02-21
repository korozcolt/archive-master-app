<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Resources\DepartmentResource\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\DepartmentResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\DepartmentResource\RelationManagers\UsersRelationManager;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DepartmentResource extends Resource
{
    use Translatable;

    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Departamentos';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(permissions: [
            'manage-departments',
            'manage-department',
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
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('branch_id', null)),
                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal')
                            ->relationship('branch', 'name', fn (Builder $query, Forms\Get $get) => $query->where('company_id', $get('company_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Seleccione primero una empresa')
                            ->visible(fn (Forms\Get $get) => $get('company_id')),
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

                Forms\Components\Section::make('Jerarquía')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('Departamento padre')
                            ->relationship('parent', 'name', fn (Builder $query, Forms\Get $get) => $query->where('company_id', $get('company_id'))
                                ->when($get('branch_id'), fn ($query) => $query->where('branch_id', $get('branch_id'))
                                )
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Departamento raíz')
                            ->visible(fn (Forms\Get $get) => $get('company_id')),
                    ]),

                Forms\Components\Section::make('Detalles')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(65535),
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
                            ->rows(3)
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.'),
                    ])
                    ->collapsed(),
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
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->branch && is_array($state)) {
                            return $record->branch->getTranslation('name', app()->getLocale());
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
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Departamento padre')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->counts('documents')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
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
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
                Tables\Filters\Filter::make('root')
                    ->label('Solo departamentos raíz')
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id'))
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
                    Tables\Actions\BulkAction::make('activateDepartments')
                        ->label('Activar Departamentos')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    Tables\Actions\BulkAction::make('deactivateDepartments')
                        ->label('Desactivar Departamentos')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            DocumentsRelationManager::class,
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
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
