<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateCompanyWizard extends CreateRecord
{
    protected static string $resource = CompanyResource::class;
    
    protected static ?string $title = 'Crear Empresa - Asistente';
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Información Básica')
                        ->description('Datos principales de la empresa')
                        ->icon('heroicon-m-building-office')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre de la Empresa')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    if (! $get('slug') && $state) {
                                        $set('slug', \Illuminate\Support\Str::slug($state));
                                    }
                                })
                                ->helperText('Nombre oficial de la empresa'),
                                
                            Forms\Components\TextInput::make('slug')
                                ->label('Identificador (Slug)')
                                ->required()
                                ->maxLength(255)
                                ->unique(Company::class, 'slug', ignoreRecord: true)
                                ->helperText('Identificador único para URLs (se genera automáticamente)'),
                                
                            Forms\Components\TextInput::make('legal_name')
                                ->label('Razón Social')
                                ->maxLength(255)
                                ->helperText('Nombre legal completo de la empresa'),
                                
                            Forms\Components\TextInput::make('tax_id')
                                ->label('RFC/NIT/Tax ID')
                                ->maxLength(50)
                                ->unique(Company::class, 'tax_id', ignoreRecord: true)
                                ->helperText('Número de identificación fiscal'),
                                
                            Forms\Components\Select::make('industry')
                                ->label('Industria/Sector')
                                ->options([
                                    'technology' => 'Tecnología',
                                    'healthcare' => 'Salud',
                                    'finance' => 'Finanzas',
                                    'education' => 'Educación',
                                    'manufacturing' => 'Manufactura',
                                    'retail' => 'Retail/Comercio',
                                    'construction' => 'Construcción',
                                    'consulting' => 'Consultoría',
                                    'government' => 'Gobierno',
                                    'nonprofit' => 'Sin fines de lucro',
                                    'other' => 'Otro'
                                ])
                                ->searchable()
                                ->helperText('Sector al que pertenece la empresa'),
                        ])
                        ->columns(2),
                        
                    Wizard\Step::make('Información de Contacto')
                        ->description('Datos de contacto y ubicación')
                        ->icon('heroicon-m-map-pin')
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->label('Correo Electrónico Principal')
                                ->email()
                                ->maxLength(255)
                                ->helperText('Email principal de contacto'),
                                
                            Forms\Components\TextInput::make('phone')
                                ->label('Teléfono Principal')
                                ->tel()
                                ->maxLength(20)
                                ->helperText('Número de teléfono principal'),
                                
                            Forms\Components\TextInput::make('website')
                                ->label('Sitio Web')
                                ->url()
                                ->maxLength(255)
                                ->helperText('URL del sitio web de la empresa'),
                                
                            Forms\Components\Textarea::make('address')
                                ->label('Dirección')
                                ->rows(3)
                                ->maxLength(500)
                                ->helperText('Dirección física de la empresa'),
                                
                            Forms\Components\TextInput::make('city')
                                ->label('Ciudad')
                                ->maxLength(100)
                                ->helperText('Ciudad donde se ubica la empresa'),
                                
                            Forms\Components\TextInput::make('state')
                                ->label('Estado/Provincia')
                                ->maxLength(100)
                                ->helperText('Estado o provincia'),
                                
                            Forms\Components\TextInput::make('country')
                                ->label('País')
                                ->maxLength(100)
                                ->default('México')
                                ->helperText('País donde se ubica la empresa'),
                                
                            Forms\Components\TextInput::make('postal_code')
                                ->label('Código Postal')
                                ->maxLength(20)
                                ->helperText('Código postal de la dirección'),
                        ])
                        ->columns(2),
                        
                    Wizard\Step::make('Configuración del Sistema')
                        ->description('Configuraciones específicas del sistema')
                        ->icon('heroicon-m-cog-6-tooth')
                        ->schema([
                            Forms\Components\Select::make('default_language')
                                ->label('Idioma por Defecto')
                                ->options([
                                    'es' => 'Español',
                                    'en' => 'English',
                                ])
                                ->default('es')
                                ->required()
                                ->helperText('Idioma predeterminado para los usuarios de esta empresa'),
                                
                            Forms\Components\Select::make('timezone')
                                ->label('Zona Horaria')
                                ->options([
                                    'America/Mexico_City' => 'México (GMT-6)',
                                    'America/New_York' => 'Nueva York (GMT-5)',
                                    'America/Los_Angeles' => 'Los Ángeles (GMT-8)',
                                    'Europe/Madrid' => 'Madrid (GMT+1)',
                                    'America/Bogota' => 'Bogotá (GMT-5)',
                                    'America/Lima' => 'Lima (GMT-5)',
                                    'America/Santiago' => 'Santiago (GMT-3)',
                                ])
                                ->default('America/Mexico_City')
                                ->required()
                                ->searchable()
                                ->helperText('Zona horaria para fechas y notificaciones'),
                                
                            Forms\Components\Select::make('currency')
                                ->label('Moneda')
                                ->options([
                                    'MXN' => 'Peso Mexicano (MXN)',
                                    'USD' => 'Dólar Americano (USD)',
                                    'EUR' => 'Euro (EUR)',
                                    'COP' => 'Peso Colombiano (COP)',
                                    'PEN' => 'Sol Peruano (PEN)',
                                    'CLP' => 'Peso Chileno (CLP)',
                                ])
                                ->default('MXN')
                                ->searchable()
                                ->helperText('Moneda utilizada en la empresa'),
                                
                            Forms\Components\Toggle::make('is_active')
                                ->label('Empresa Activa')
                                ->default(true)
                                ->helperText('Las empresas inactivas no pueden acceder al sistema'),
                                
                            Forms\Components\TextInput::make('max_users')
                                ->label('Máximo de Usuarios')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(10000)
                                ->default(50)
                                ->helperText('Número máximo de usuarios permitidos'),
                                
                            Forms\Components\TextInput::make('max_documents')
                                ->label('Máximo de Documentos')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(1000000)
                                ->default(10000)
                                ->helperText('Número máximo de documentos permitidos'),
                        ])
                        ->columns(2),
                        
                    Wizard\Step::make('Branding y Personalización')
                        ->description('Logo, colores y personalización')
                        ->icon('heroicon-m-paint-brush')
                        ->schema([
                            Forms\Components\FileUpload::make('logo')
                                ->label('Logo de la Empresa')
                                ->image()
                                ->directory('company-logos')
                                ->maxSize(2048)
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth('300')
                                ->imageResizeTargetHeight('300')
                                ->helperText('Logo de la empresa (máx. 2MB, recomendado: 300x300px)'),
                                
                            Forms\Components\ColorPicker::make('primary_color')
                                ->label('Color Primario')
                                ->default('#3B82F6')
                                ->helperText('Color principal de la marca'),
                                
                            Forms\Components\ColorPicker::make('secondary_color')
                                ->label('Color Secundario')
                                ->default('#64748B')
                                ->helperText('Color secundario de la marca'),
                                
                            Forms\Components\Textarea::make('description')
                                ->label('Descripción de la Empresa')
                                ->rows(4)
                                ->maxLength(1000)
                                ->helperText('Descripción breve de la empresa y sus actividades'),
                                
                            Forms\Components\KeyValue::make('settings')
                                ->label('Configuraciones Adicionales')
                                ->keyLabel('Configuración')
                                ->valueLabel('Valor')
                                ->helperText('Configuraciones personalizadas para la empresa'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull()
                ->skippable()
                ->persistStepInQueryString()
            ]);
    }
    
    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return Notification::make()
            ->success()
            ->title('Empresa creada exitosamente')
            ->body('La empresa ha sido creada y está lista para configurar usuarios y documentos.');
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}