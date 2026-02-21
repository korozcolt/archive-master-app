<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\CategoriesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\DepartmentsRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\StatusesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\TagsRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use App\Models\DocumentAiRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompanyResource extends Resource
{
    use Translatable;

    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin']);
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
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('legal_name')
                            ->label('Razón Social')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('NIT/Identificación fiscal')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website')
                            ->label('Sitio web')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Personalización')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('200')
                            ->imageResizeTargetHeight('200')
                            ->directory('company-logos'),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color primario'),
                        Forms\Components\ColorPicker::make('secondary_color')
                            ->label('Color secundario'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Activa')
                            ->default(true),
                        Forms\Components\Textarea::make('settings')
                            ->label('Configuración adicional (JSON)')
                            ->rows(3)
                            ->helperText('Configuración en formato JSON. Dejar vacío si no es necesario.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Configuración IA')
                    ->description('Configuración de proveedor IA por compañía (BYOK).')
                    ->schema([
                        Forms\Components\Select::make('ai_setting.provider')
                            ->label('Proveedor IA')
                            ->options([
                                'none' => 'Ninguno',
                                'openai' => 'OpenAI',
                                'gemini' => 'Gemini',
                            ])
                            ->default('none')
                            ->native(false)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\Toggle::make('ai_setting.is_enabled')
                            ->label('IA habilitada')
                            ->default(false)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\Placeholder::make('api_key_status')
                            ->label('Estado de API key')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return 'Sin configuración guardada.';
                                }

                                return filled($record->aiSetting?->api_key_encrypted)
                                    ? 'API key configurada ✅'
                                    : 'Sin API key configurada.';
                            }),
                        Forms\Components\TextInput::make('ai_setting.api_key_encrypted')
                            ->label('API key (oculta)')
                            ->password()
                            ->revealable()
                            ->placeholder('Ingresa una nueva key para actualizar')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component): void {
                                $component->state('');
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydratedWhenHidden(false),
                        Forms\Components\TextInput::make('ai_setting.daily_doc_limit')
                            ->label('Límite diario de documentos')
                            ->numeric()
                            ->minValue(1)
                            ->default(100)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\TextInput::make('ai_setting.max_pages_per_doc')
                            ->label('Máximo de páginas por documento')
                            ->numeric()
                            ->minValue(1)
                            ->default(100)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\TextInput::make('ai_setting.monthly_budget_cents')
                            ->label('Presupuesto mensual (centavos)')
                            ->numeric()
                            ->minValue(0)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\Toggle::make('ai_setting.store_outputs')
                            ->label('Guardar resultados de IA')
                            ->default(true)
                            ->dehydratedWhenHidden(false),
                        Forms\Components\Toggle::make('ai_setting.redact_pii')
                            ->label('Redactar PII antes de enviar')
                            ->default(true)
                            ->dehydratedWhenHidden(false),
                    ])
                    ->columns(2)
                    ->hidden(fn (?Company $record): bool => $record === null)
                    ->collapsible(),

                Forms\Components\Section::make('Observabilidad IA')
                    ->description('Métricas rápidas de ejecución y costo por compañía.')
                    ->schema([
                        Forms\Components\Placeholder::make('ai_observability_runs_today')
                            ->label('Runs IA hoy')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return '0';
                                }

                                $count = DocumentAiRun::query()
                                    ->where('company_id', $record->id)
                                    ->whereDate('created_at', now()->toDateString())
                                    ->count();

                                return (string) $count;
                            }),
                        Forms\Components\Placeholder::make('ai_observability_runs_success_month')
                            ->label('Runs exitosos (mes)')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return '0';
                                }

                                $count = DocumentAiRun::query()
                                    ->where('company_id', $record->id)
                                    ->where('status', 'success')
                                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                                    ->count();

                                return (string) $count;
                            }),
                        Forms\Components\Placeholder::make('ai_observability_cost_month')
                            ->label('Costo mensual acumulado')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return '$0.00';
                                }

                                $cents = (int) DocumentAiRun::query()
                                    ->where('company_id', $record->id)
                                    ->where('status', 'success')
                                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                                    ->sum('cost_cents');

                                return '$'.number_format($cents / 100, 2);
                            }),
                        Forms\Components\Placeholder::make('ai_observability_failures_24h')
                            ->label('Fallos proveedor (24h)')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return 'OpenAI: 0 | Gemini: 0';
                                }

                                $runs = DocumentAiRun::query()
                                    ->where('company_id', $record->id)
                                    ->where('status', 'failed')
                                    ->where('created_at', '>=', now()->subDay())
                                    ->get(['provider']);

                                $openAi = $runs->where('provider', 'openai')->count();
                                $gemini = $runs->where('provider', 'gemini')->count();

                                return "OpenAI: {$openAi} | Gemini: {$gemini}";
                            }),
                        Forms\Components\Placeholder::make('ai_observability_last_error')
                            ->label('Último error')
                            ->content(function (?Company $record): string {
                                if (! $record) {
                                    return 'Sin datos';
                                }

                                $lastError = DocumentAiRun::query()
                                    ->where('company_id', $record->id)
                                    ->where('status', 'failed')
                                    ->whereNotNull('error_message')
                                    ->latest('id')
                                    ->value('error_message');

                                if (! $lastError) {
                                    return 'Sin errores recientes';
                                }

                                return (string) str($lastError)->limit(120);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->hidden(fn (?Company $record): bool => $record === null)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular(),
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
                Tables\Columns\TextColumn::make('legal_name')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tax_id')
                    ->label('NIT/ID Fiscal')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('branches_count')
                    ->label('Sucursales')
                    ->counts('branches')
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
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BranchesRelationManager::class,
            DepartmentsRelationManager::class,
            UsersRelationManager::class,
            CategoriesRelationManager::class,
            TagsRelationManager::class,
            StatusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'create-wizard' => Pages\CreateCompanyWizard::route('/create-wizard'),
            'view' => Pages\ViewCompany::route('/{record}'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
            'ai-observability' => Pages\AiObservability::route('/{record}/ai-observability'),
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
