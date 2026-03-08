<?php

namespace App\Filament\Resources;

use App\Enums\DocumentAccessLevel;
use App\Filament\ResourceAccess;
use App\Filament\Resources\DocumentaryTypeResource\Pages;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DocumentaryTypeResource extends Resource
{
    protected static ?string $model = DocumentaryType::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Tipos Documentales';

    protected static ?string $modelLabel = 'tipo documental';

    protected static ?string $pluralModelLabel = 'tipos documentales';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 5;

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
                Forms\Components\Section::make('Tipo documental')
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
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('access_level_default')
                            ->label('Nivel de acceso por defecto')
                            ->options(collect(DocumentAccessLevel::cases())->mapWithKeys(fn (DocumentAccessLevel $case): array => [$case->value => $case->getLabel()])->all())
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('subseries.series.code')
                    ->label('Serie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subseries.code')
                    ->label('Subserie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tipo documental')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('access_level_default')
                    ->label('Acceso por defecto')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof DocumentAccessLevel ? $state->getLabel() : (string) $state),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('subseries')
                    ->label('Subserie')
                    ->relationship('subseries', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListDocumentaryTypes::route('/'),
            'create' => Pages\CreateDocumentaryType::route('/create'),
            'edit' => Pages\EditDocumentaryType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'subseries.series']);
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
