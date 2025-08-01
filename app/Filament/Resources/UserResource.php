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

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $navigationGroup = 'Administration';

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
                        Forms\Components\Section::make('Personal Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('position')
                                    ->label('Position')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Organizational Assignment')
                            ->schema([
                                Forms\Components\Select::make('company_id')
                                    ->label('Company')
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => Auth::user()->hasRole('super_admin') ? null : Auth::user()->company_id)
                                    ->disabled(fn () => !Auth::user()->hasRole('super_admin') && Auth::user()->company_id)
                                    ->reactive(),
                                Forms\Components\Select::make('branch_id')
                                    ->label('Branch')
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
                                    ->label('Department')
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

                        Forms\Components\Section::make('Authentication')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->autocomplete('new-password')
                                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->confirmed(),
                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label('Password Confirmation')
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
                        Forms\Components\Section::make('Image and Status')
                            ->schema([
                                Forms\Components\FileUpload::make('profile_photo')
                                    ->label('Profile Photo')
                                    ->image()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('200')
                                    ->imageResizeTargetHeight('200')
                                    ->directory('profile-photos'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Is Active')
                                    ->default(true),
                            ]),

                        Forms\Components\Section::make('Preferences')
                            ->schema([
                                Forms\Components\Select::make('language')
                                    ->label('Language')
                                    ->options([
                                        'es' => 'Español',
                                        'en' => 'Inglés',
                                    ])
                                    ->default('es'),
                                Forms\Components\Select::make('timezone')
                                    ->label('Timezone')
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
                                    ->label(__('filament::resources.user.fields.settings'))
                                    ->keyLabel(__('filament::resources.user.fields.settings_key'))
                                    ->valueLabel(__('filament::resources.user.fields.settings_value'))
                                    ->keyPlaceholder(__('filament::resources.user.placeholders.enter_key'))
                                    ->valuePlaceholder(__('filament::resources.user.placeholders.enter_value'))
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
                    ->label('Profile Photo')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: Auth::user()->hasRole('super_admin') ? false : true),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
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
                    ->label('Is Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Active Users')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('company')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->visible(fn() => Auth::user()->hasRole('super_admin')),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Branch')
                    ->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Role')
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
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Password Confirmation')
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
                        ->label('Activate Users')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivateUsers')
                        ->label('Deactivate Users')
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
            'create-wizard' => Pages\CreateUserWizard::route('/create-wizard'),
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
