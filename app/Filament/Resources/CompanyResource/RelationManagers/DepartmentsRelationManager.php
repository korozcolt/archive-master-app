<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DepartmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'departments';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Departamentos';

    protected static ?string $label = 'Departamento';

    protected static ?string $pluralLabel = 'Departamentos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Departamento')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(255),
                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Departamento padre')
                            ->relationship('parent', 'name', fn (Builder $query) => $query->where('company_id', $this->getOwnerRecord()->id))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query) {
                // Aplica withoutGlobalScopes directamente
                return $query->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
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
                Tables\Filters\Filter::make('active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
