<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\DocumentarySeriesResource\Pages;
use App\Models\Department;
use App\Models\DocumentarySeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DocumentarySeriesResource extends Resource
{
    protected static ?string $model = DocumentarySeries::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Series TRD';

    protected static ?string $modelLabel = 'serie TRD';

    protected static ?string $pluralModelLabel = 'series TRD';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 3;

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
                Forms\Components\Section::make('Serie documental')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()?->company_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('department_id', null)),
                        Forms\Components\Select::make('department_id')
                            ->label('Dependencia / oficina productora')
                            ->options(fn (Forms\Get $get): array => Department::query()
                                ->where('company_id', $get('company_id') ?: Auth::user()?->company_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('General para toda la empresa'),
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
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Dependencia')
                    ->searchable()
                    ->sortable()
                    ->placeholder('General'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subseries_count')
                    ->label('Subseries')
                    ->counts('subseries')
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
                Tables\Filters\SelectFilter::make('department')
                    ->label('Dependencia')
                    ->relationship('department', 'name')
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
            'index' => Pages\ListDocumentarySeries::route('/'),
            'create' => Pages\CreateDocumentarySeries::route('/create'),
            'edit' => Pages\EditDocumentarySeries::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'department'])->withCount('subseries');
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
