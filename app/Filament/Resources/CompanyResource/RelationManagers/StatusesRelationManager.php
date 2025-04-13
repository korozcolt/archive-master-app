<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Enums\DocumentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class StatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'statuses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Estados';

    protected static ?string $label = 'Estado';

    protected static ?string $pluralLabel = 'Estados';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Estado')
                    ->schema([
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
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
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
                Tables\Filters\Filter::make('is_initial')
                    ->label('Estados iniciales')
                    ->query(fn (Builder $query): Builder => $query->where('is_initial', true))
                    ->toggle(),
                Tables\Filters\Filter::make('is_final')
                    ->label('Estados finales')
                    ->query(fn (Builder $query): Builder => $query->where('is_final', true))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('createDefaultStatuses')
                    ->label('Crear Estados Predeterminados')
                    ->action(function () {
                        $companyId = $this->getOwnerRecord()->id;

                        // Crear estados predeterminados basados en el enum DocumentStatus
                        foreach (DocumentStatus::cases() as $status) {
                            $this->getOwnerRecord()->statuses()->firstOrCreate(
                                [
                                    'slug' => $status->value,
                                ],
                                [
                                    'name' => $status->getLabel(),
                                    'color' => $status->getColor(),
                                    'icon' => $status->getIcon(),
                                    'is_initial' => $status === DocumentStatus::Received || $status === DocumentStatus::Draft,
                                    'is_final' => $status === DocumentStatus::Archived || $status === DocumentStatus::Rejected || $status === DocumentStatus::Approved,
                                    'active' => true,
                                ]
                            );
                        }
                    })
                    ->color('success')
                    ->icon('heroicon-o-bolt'),
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
