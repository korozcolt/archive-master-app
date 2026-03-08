<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\DocumentarySubseriesResource\Pages;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DocumentarySubseriesResource extends Resource
{
    protected static ?string $model = DocumentarySubseries::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Subseries TRD';

    protected static ?string $modelLabel = 'subserie TRD';

    protected static ?string $pluralModelLabel = 'subseries TRD';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 4;

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
                Forms\Components\Section::make('Subserie documental')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()?->company_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('documentary_series_id')
                            ->label('Serie')
                            ->options(fn (Forms\Get $get): array => DocumentarySeries::query()
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
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
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
                Tables\Columns\TextColumn::make('series.code')
                    ->label('Serie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Subserie')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('documentary_types_count')
                    ->label('Tipos')
                    ->counts('documentaryTypes')
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('series')
                    ->label('Serie')
                    ->relationship('series', 'name')
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
            'index' => Pages\ListDocumentarySubseries::route('/'),
            'create' => Pages\CreateDocumentarySubseries::route('/create'),
            'edit' => Pages\EditDocumentarySubseries::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'series'])->withCount('documentaryTypes');
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
