<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\BusinessCalendarResource\Pages;
use App\Models\BusinessCalendar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BusinessCalendarResource extends Resource
{
    protected static ?string $model = BusinessCalendar::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Calendarios Hábiles';

    protected static ?string $modelLabel = 'calendario hábil';

    protected static ?string $pluralModelLabel = 'calendarios hábiles';

    protected static ?string $navigationGroup = 'Gobernanza Documental';

    protected static ?int $navigationSort = 2;

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
                Forms\Components\Section::make('Calendario hábil')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->default(Auth::user()?->company_id)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('country_code')
                            ->label('País')
                            ->default('CO')
                            ->required()
                            ->maxLength(5),
                        Forms\Components\TextInput::make('timezone')
                            ->label('Zona horaria')
                            ->default('America/Bogota')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\CheckboxList::make('weekend_days')
                            ->label('Días no hábiles recurrentes')
                            ->options([
                                '0' => 'Domingo',
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                            ])
                            ->columns(4)
                            ->default(['0', '6'])
                            ->required(),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Calendario por defecto')
                            ->default(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Excepciones por fecha')
                    ->description('Registra festivos o días hábiles especiales para este calendario.')
                    ->schema([
                        Forms\Components\Repeater::make('days')
                            ->relationship()
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->label('Fecha')
                                    ->required(),
                                Forms\Components\Toggle::make('is_business_day')
                                    ->label('Es hábil')
                                    ->default(false),
                                Forms\Components\TextInput::make('note')
                                    ->label('Nota')
                                    ->maxLength(255),
                            ])
                            ->defaultItems(0)
                            ->columns(3)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
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
                    ->label('Calendario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('País')
                    ->sortable(),
                Tables\Columns\TextColumn::make('timezone')
                    ->label('Zona horaria')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('weekend_days')
                    ->label('No hábiles recurrentes')
                    ->formatStateUsing(function (mixed $state): string {
                        if (is_string($state) && $state !== '') {
                            $decoded = json_decode($state, true);

                            if (is_array($decoded)) {
                                $state = $decoded;
                            }
                        }

                        return collect(is_array($state) ? $state : [])
                            ->map(fn (string $value): string => match ($value) {
                                '0' => 'Dom',
                                '1' => 'Lun',
                                '2' => 'Mar',
                                '3' => 'Mié',
                                '4' => 'Jue',
                                '5' => 'Vie',
                                '6' => 'Sáb',
                                default => $value,
                            })
                            ->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('days_count')
                    ->label('Excepciones')
                    ->counts('days')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Calendario por defecto'),
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
            'index' => Pages\ListBusinessCalendars::route('/'),
            'create' => Pages\CreateBusinessCalendar::route('/create'),
            'edit' => Pages\EditBusinessCalendar::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company'])->withCount('days');
        $user = Auth::user();

        if ($user && ! $user->hasRole('super_admin') && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
