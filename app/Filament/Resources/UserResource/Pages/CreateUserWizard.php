<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Role as AppRole;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class CreateUserWizard extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Crear Usuario - Asistente';

    protected function beforeCreate(): void
    {
        if (isset($this->data['roles']) && is_array($this->data['roles'])) {
            $this->data['roles'] = UserResource::sanitizeAssignableRoles($this->data['roles']);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Información Personal')
                        ->description('Datos básicos del usuario')
                        ->icon('heroicon-m-user')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre Completo')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Ingresa el nombre completo del usuario'),

                            Forms\Components\TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->required()
                                ->unique(User::class, 'email', ignoreRecord: true)
                                ->maxLength(255)
                                ->helperText('Este será el correo para iniciar sesión'),

                            Forms\Components\TextInput::make('phone')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(20)
                                ->helperText('Número de teléfono de contacto'),

                            Forms\Components\DatePicker::make('birth_date')
                                ->label('Fecha de Nacimiento')
                                ->native(false)
                                ->maxDate(now()->subYears(16))
                                ->helperText('Opcional: Fecha de nacimiento del usuario'),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Credenciales de Acceso')
                        ->description('Configuración de contraseña y acceso')
                        ->icon('heroicon-m-key')
                        ->schema([
                            Forms\Components\TextInput::make('password')
                                ->label('Contraseña')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->live(debounce: 500)
                                ->helperText('Mínimo 8 caracteres. Se recomienda usar mayúsculas, minúsculas, números y símbolos'),

                            Forms\Components\TextInput::make('password_confirmation')
                                ->label('Confirmar Contraseña')
                                ->password()
                                ->required()
                                ->same('password')
                                ->dehydrated(false)
                                ->helperText('Repite la contraseña para confirmar'),

                            Forms\Components\Toggle::make('email_verified_at')
                                ->label('Marcar Email como Verificado')
                                ->default(true)
                                ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                                ->helperText('Si está activado, el usuario no necesitará verificar su email'),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Usuario Activo')
                                ->default(true)
                                ->helperText('Los usuarios inactivos no pueden iniciar sesión'),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Asignación Organizacional')
                        ->description('Empresa, sucursal y departamento')
                        ->icon('heroicon-m-building-office')
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->label('Empresa')
                                ->relationship('company', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('branch_id', null);
                                    $set('department_id', null);
                                })
                                ->helperText('Selecciona la empresa a la que pertenece el usuario'),

                            Forms\Components\Select::make('branch_id')
                                ->label('Sucursal')
                                ->relationship('branch', 'name', fn (Builder $query, Get $get) => $query->where('company_id', $get('company_id'))
                                )
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                                ->helperText('Opcional: Sucursal específica del usuario'),

                            Forms\Components\Select::make('department_id')
                                ->label('Departamento')
                                ->relationship('department', 'name', fn (Builder $query, Get $get) => $query->where('company_id', $get('company_id'))
                                    ->when($get('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId)
                                    )
                                )
                                ->searchable()
                                ->preload()
                                ->helperText('Opcional: Departamento al que pertenece el usuario'),

                            Forms\Components\TextInput::make('position')
                                ->label('Cargo/Posición')
                                ->maxLength(100)
                                ->helperText('Cargo o posición del usuario en la empresa'),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Roles y Permisos')
                        ->description('Asignación de roles y permisos')
                        ->icon('heroicon-m-shield-check')
                        ->schema([
                            Forms\Components\CheckboxList::make('roles')
                                ->label('Roles del Usuario')
                                ->relationship(
                                    'roles',
                                    'name',
                                    fn (Builder $query) => $query->where('name', '!=', AppRole::SuperAdmin->value),
                                )
                                ->getOptionLabelFromRecordUsing(function (SpatieRole $record): string {
                                    $enum = AppRole::tryFrom($record->name);

                                    return $enum?->getLabel() ?? str($record->name)->replace('_', ' ')->title()->toString();
                                })
                                ->descriptions(function (): array {
                                    return SpatieRole::query()
                                        ->where('name', '!=', AppRole::SuperAdmin->value)
                                        ->get(['id', 'name'])
                                        ->mapWithKeys(function (SpatieRole $role): array {
                                            $enum = AppRole::tryFrom($role->name);

                                            if (! $enum) {
                                                return [];
                                            }

                                            return [(string) $role->id => $enum->getDescription()];
                                        })
                                        ->toArray();
                                })
                                ->columns(2)
                                ->helperText('Selecciona los roles que tendrá el usuario'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Notas Adicionales')
                                ->rows(3)
                                ->maxLength(500)
                                ->helperText('Cualquier información adicional sobre el usuario'),
                        ])
                        ->columns(1),

                    Wizard\Step::make('Configuración Adicional')
                        ->description('Preferencias y configuración final')
                        ->icon('heroicon-m-cog-6-tooth')
                        ->schema([
                            Forms\Components\Select::make('language')
                                ->label('Idioma Preferido')
                                ->options([
                                    'es' => 'Español',
                                    'en' => 'English',
                                ])
                                ->default('es')
                                ->helperText('Idioma de la interfaz para este usuario'),

                            Forms\Components\Select::make('timezone')
                                ->label('Zona Horaria')
                                ->options([
                                    'America/Mexico_City' => 'México (GMT-6)',
                                    'America/New_York' => 'Nueva York (GMT-5)',
                                    'America/Los_Angeles' => 'Los Ángeles (GMT-8)',
                                    'Europe/Madrid' => 'Madrid (GMT+1)',
                                ])
                                ->default('America/Bogota')
                                ->searchable()
                                ->helperText('Zona horaria para fechas y notificaciones'),

                            Forms\Components\Toggle::make('receive_notifications')
                                ->label('Recibir Notificaciones por Email')
                                ->default(true)
                                ->helperText('El usuario recibirá notificaciones importantes por correo'),

                            Forms\Components\FileUpload::make('avatar')
                                ->label('Foto de Perfil')
                                ->image()
                                ->directory('avatars')
                                ->maxSize(2048)
                                ->imageResizeMode('cover')
                                ->imageCropAspectRatio('1:1')
                                ->imageResizeTargetWidth('200')
                                ->imageResizeTargetHeight('200')
                                ->helperText('Opcional: Foto de perfil del usuario (máx. 2MB)'),
                        ])
                        ->columns(2),
                ])
                    ->columnSpanFull()
                    ->skippable()
                    ->persistStepInQueryString(),
            ]);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return Notification::make()
            ->success()
            ->title('Usuario creado exitosamente')
            ->body('El usuario ha sido creado y puede iniciar sesión en el sistema.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
