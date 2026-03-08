<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\SlaPolicyResource\Pages;
use App\Models\BusinessCalendar;
use App\Models\SlaPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SlaPolicyResource extends Resource
{
    protected static ?string $model = SlaPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Políticas SLA';

    protected static ?string $modelLabel = 'política SLA';

    protected static ?string $pluralModelLabel = 'políticas SLA';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin', 'branch_admin', 'archive_manager']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificación legal')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()?->company_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('business_calendar_id')
                            ->label('Calendario hábil')
                            ->options(fn (Forms\Get $get): array => BusinessCalendar::query()
                                ->where('company_id', $get('company_id') ?: Auth::user()?->company_id)
                                ->orderBy('is_default', 'desc')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre visible')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('legal_basis')
                            ->label('Base legal')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Términos y alertas')
                    ->schema([
                        Forms\Components\TextInput::make('response_term_days')
                            ->label('Plazo de respuesta (días hábiles)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TagsInput::make('warning_days')
                            ->label('Alertas previas')
                            ->default([])
                            ->afterStateHydrated(function (Forms\Components\TagsInput $component, mixed $state): void {
                                $component->state(is_array($state) ? $state : []);
                            })
                            ->helperText('Ejemplo: 3,1'),
                        Forms\Components\TextInput::make('escalation_days')
                            ->label('Escalamiento tras vencimiento')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('remission_deadline_days')
                            ->label('Remisión por incompetencia')
                            ->numeric()
                            ->minValue(0)
                            ->default(5)
                            ->required(),
                        Forms\Components\Toggle::make('requires_subsanation')
                            ->label('Permitir suspensión por subsanación')
                            ->default(true),
                        Forms\Components\Toggle::make('allows_extension')
                            ->label('Permitir prórroga motivada')
                            ->default(true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Metadatos adicionales')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadatos')
                            ->keyLabel('Campo')
                            ->valueLabel('Valor')
                            ->columnSpanFull(),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Política')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_term_days')
                    ->label('Plazo')
                    ->suffix(' días')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warning_days')
                    ->label('Alertas')
                    ->badge()
                    ->formatStateUsing(function (mixed $state): string {
                        if (is_array($state)) {
                            return implode(', ', $state);
                        }

                        if (is_string($state) && $state !== '') {
                            $decoded = json_decode($state, true);

                            if (is_array($decoded)) {
                                return implode(', ', $decoded);
                            }

                            return $state;
                        }

                        return 'Sin alertas';
                    }),
                Tables\Columns\TextColumn::make('businessCalendar.name')
                    ->label('Calendario')
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_subsanation')
                    ->label('Subsanación')
                    ->boolean(),
                Tables\Columns\IconColumn::make('allows_extension')
                    ->label('Prórroga')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlaPolicies::route('/'),
            'create' => Pages\CreateSlaPolicy::route('/create'),
            'edit' => Pages\EditSlaPolicy::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'businessCalendar']);
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
