<?php

namespace App\Filament\Resources;

use App\Enums\ArchivePhase;
use App\Enums\FinalDisposition;
use App\Filament\ResourceAccess;
use App\Filament\Resources\RetentionScheduleResource\Pages;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RetentionScheduleResource extends Resource
{
    protected static ?string $model = RetentionSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Tablas de Retención';

    protected static ?string $modelLabel = 'tabla de retención';

    protected static ?string $pluralModelLabel = 'tablas de retención';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin', 'branch_admin', 'archive_manager']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tabla de retención documental')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()?->company_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('documentary_subseries_id')
                            ->label('Subserie')
                            ->options(fn (Forms\Get $get): array => DocumentarySubseries::query()
                                ->where('company_id', $get('company_id') ?: Auth::user()?->company_id)
                                ->orderBy('code')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('documentary_type_id')
                            ->label('Tipo documental')
                            ->options(fn (Forms\Get $get): array => DocumentaryType::query()
                                ->where('company_id', $get('company_id') ?: Auth::user()?->company_id)
                                ->when($get('documentary_subseries_id'), fn (Builder $query, $subseriesId) => $query->where('documentary_subseries_id', $subseriesId))
                                ->orderBy('code')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('archive_phase')
                            ->label('Fase archivística inicial')
                            ->options(collect(ArchivePhase::cases())->mapWithKeys(fn (ArchivePhase $case): array => [$case->value => $case->getLabel()])->all())
                            ->required(),
                        Forms\Components\TextInput::make('management_years')
                            ->label('Años en gestión')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('central_years')
                            ->label('Años en archivo central')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\TextInput::make('historical_action')
                            ->label('Acción histórica')
                            ->maxLength(255),
                        Forms\Components\Select::make('final_disposition')
                            ->label('Disposición final')
                            ->options(collect(FinalDisposition::cases())->mapWithKeys(fn (FinalDisposition $case): array => [$case->value => $case->getLabel()])->all())
                            ->required(),
                        Forms\Components\Textarea::make('legal_basis')
                            ->label('Soporte normativo')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('documentarySubseries.series.code')
                    ->label('Serie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('documentarySubseries.code')
                    ->label('Subserie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('documentaryType.code')
                    ->label('Tipo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('archive_phase')
                    ->label('Fase')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ArchivePhase ? $state->getLabel() : (string) $state),
                Tables\Columns\TextColumn::make('management_years')
                    ->label('Gestión')
                    ->suffix(' años'),
                Tables\Columns\TextColumn::make('central_years')
                    ->label('Central')
                    ->suffix(' años'),
                Tables\Columns\TextColumn::make('final_disposition')
                    ->label('Disposición')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof FinalDisposition ? $state->getLabel() : (string) $state),
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
                Tables\Filters\SelectFilter::make('archive_phase')
                    ->label('Fase')
                    ->options(collect(ArchivePhase::cases())->mapWithKeys(fn (ArchivePhase $case): array => [$case->value => $case->getLabel()])->all()),
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
            'index' => Pages\ListRetentionSchedules::route('/'),
            'create' => Pages\CreateRetentionSchedule::route('/create'),
            'edit' => Pages\EditRetentionSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'documentarySubseries.series', 'documentaryType']);
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
