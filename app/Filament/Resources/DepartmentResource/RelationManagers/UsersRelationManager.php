<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use App\Enums\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Usuarios';

    protected static ?string $label = 'Usuario';

    protected static ?string $pluralLabel = 'Usuarios';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Usuario')
                    ->schema([
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->company_id),
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->branch_id),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('position')
                            ->label('Cargo')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Seguridad')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(collect(Role::cases())->pluck('value', 'value')
                                ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]))
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preferencias')
                    ->schema([
                        Forms\Components\Select::make('language')
                            ->label('Idioma')
                            ->options([
                                'es' => 'Español',
                                'en' => 'Inglés',
                            ])
                            ->default('es'),
                        Forms\Components\Select::make('timezone')
                            ->label('Zona horaria')
                            ->options([
                                'America/Bogota' => 'Colombia (Bogotá)',
                                'America/Mexico_City' => 'México (Ciudad de México)',
                                'America/Lima' => 'Perú (Lima)',
                                'America/New_York' => 'EEUU (Nueva York)',
                                'America/Chicago' => 'EEUU (Chicago)',
                                'America/Denver' => 'EEUU (Denver)',
                                'America/Los_Angeles' => 'EEUU (Los Ángeles)',
                                'Europe/Madrid' => 'España (Madrid)',
                            ])
                            ->default('America/Bogota'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_documents_count')
                    ->label('Documentos creados')
                    ->counts('createdDocuments')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assigned_documents_count')
                    ->label('Documentos asignados')
                    ->counts('assignedDocuments')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo activos')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['department_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activateUsers')
                        ->label('Activar Usuarios')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivateUsers')
                        ->label('Desactivar Usuarios')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }
}
