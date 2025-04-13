<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Sucursales';

    protected static ?string $label = 'Sucursal';

    protected static ?string $pluralLabel = 'Sucursales';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Sucursal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),

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
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.')
                    ]),
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
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->label('Estado/Provincia')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('departments_count')
                    ->label('Departamentos')
                    ->counts('departments')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('active')
                    ->label('Solo activas')
                    ->query(fn (Builder $query): Builder => $query->where('active', true))
                    ->toggle(),
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
