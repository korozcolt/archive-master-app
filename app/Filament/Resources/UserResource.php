<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\AssignedDocumentsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\DocumentsRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Collection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Información personal')
                            ->schema([
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

                        Forms\Components\Section::make('Asignación organizacional')
                            ->schema([
                                Forms\Components\Select::make('company_id')
                                    ->label('Empresa')
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => Auth::user()->hasRole('super_admin') ? null : Auth::user()->company_id)
                                    ->disabled(fn () => !Auth::user()->hasRole('super_admin') && Auth::user()->company_id)
                                    ->reactive(),
                                Forms\Components\Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship('branch', 'name', function (Builder $query, callable $get) {
                                        $companyId = $get('company_id');
                                        if ($companyId) {
                                            $query->where('company_id', $companyId);
                                        }
                                        return $query;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->reactive(),
                                Forms\Components\Select::make('department_id')
                                    ->label('Departamento')
                                    ->relationship('department', 'name', function (Builder $query, callable $get) {
                                        $companyId = $get('company_id');
                                        if ($companyId) {
                                            $query->where('company_id', $companyId);
                                        }
                                        return $query;
                                    })
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Acceso y seguridad')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->autocomplete('new-password')
                                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->confirmed(),
                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label('Confirmar contraseña')
                                    ->password()
                                    ->autocomplete('new-password')
                                    ->requiredWith('password'),
                                Forms\Components\Select::make('roles')
                                    ->label('Roles')
                                    ->multiple()
                                    ->options(function () {
                                        // Los superadmins pueden asignar cualquier rol
                                        if (Auth::user()->hasRole('super_admin')) {
                                            return collect(Role::cases())->pluck('value', 'value')
                                                ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]);
                                        }

                                        // Los demás usuarios no pueden asignar super_admin
                                        return collect(Role::cases())
                                            ->filter(fn ($role) => $role !== Role::SuperAdmin)
                                            ->pluck('value', 'value')
                                            ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]);
                                    })
                                    ->searchable(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Imagen y estado')
                            ->schema([
                                Forms\Components\FileUpload::make('profile_photo')
                                    ->label('Foto de perfil')
                                    ->image()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('200')
                                    ->imageResizeTargetHeight('200')
                                    ->directory('profile-photos'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Usuario activo')
                                    ->default(true),
                            ]),

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
                                Forms\Components\KeyValue::make('settings')
                                    ->label('Configuración adicional')
                                    ->keyLabel('Clave')
                                    ->valueLabel('Valor')
                                    ->keyPlaceholder('Ingrese una clave')
                                    ->valuePlaceholder('Ingrese un valor')
                                    ->columnSpan('full'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->label('Foto')
                    ->circular(),
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
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: Auth::user()->hasRole('super_admin') ? false : true),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    ->icon(fn (string $state): string => match($state) {
                        Role::SuperAdmin->value => 'heroicon-o-shield-check',
                        Role::Admin->value => 'heroicon-o-user-circle',
                        Role::RegularUser->value => 'heroicon-o-user',
                        default => 'heroicon-o-user',
                    })
                    ->color(fn (string $state): string => match($state) {
                        Role::SuperAdmin->value => 'danger',
                        Role::Admin->value => 'warning',
                        Role::RegularUser->value => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Usuarios activos')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->visible(fn() => Auth::user()->hasRole('super_admin')),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departamento')
                    ->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->options(collect(Role::cases())->pluck('value', 'value')
                        ->mapWithKeys(fn ($value, $key) => [$value => Role::from($value)->getLabel()]))
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->whereHas('roles', function ($query) use ($data) {
                                $query->where('name', $data['value']);
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('resetPassword')
                    ->label('Restablecer contraseña')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => Hash::make($data['password']),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activateUsers')
                        ->label('Activar usuarios')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivateUsers')
                        ->label('Desactivar usuarios')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            AssignedDocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Si no es super_admin, solo mostrar usuarios de su empresa
        if (!Auth::user()->hasRole('super_admin')) {
            $query->where('company_id', Auth::user()->company_id);
        }

        return $query;
    }
}
