<?php

namespace App\Filament\Resources;

use App\Filament\ResourceAccess;
use App\Filament\Resources\ScheduledReportResource\Pages;
use App\Models\ScheduledReport;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ScheduledReportResource extends Resource
{
    protected static ?string $model = ScheduledReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Reportes Programados';

    protected static ?string $modelLabel = 'Reporte Programado';

    protected static ?string $pluralModelLabel = 'Reportes Programados';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return ResourceAccess::allows(roles: ['admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información General')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Reporte')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('user_id')
                                    ->label('Usuario')
                                    ->relationship('user', 'name')
                                    ->default(Auth::id())
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Configuración del Reporte')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('report_config.report_type')
                                    ->label('Tipo de Reporte')
                                    ->options([
                                        'documents' => 'Documentos',
                                        'users' => 'Usuarios',
                                        'departments' => 'Departamentos',
                                    ])
                                    ->required()
                                    ->default('documents'),

                                Forms\Components\Select::make('report_config.export_format')
                                    ->label('Formato de Exportación')
                                    ->options([
                                        'pdf' => 'PDF',
                                        'xlsx' => 'Excel',
                                        'csv' => 'CSV',
                                    ])
                                    ->required()
                                    ->default('pdf'),
                            ]),

                        Forms\Components\TagsInput::make('report_config.columns')
                            ->label('Columnas a Incluir')
                            ->placeholder('Escriba las columnas y presione Enter')
                            ->suggestions([
                                'title', 'description', 'status_id', 'created_at',
                                'updated_at', 'due_date', 'completed_at', 'user_id',
                                'department_id', 'category_id', 'priority',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Programación')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('schedule_frequency')
                                    ->label('Frecuencia')
                                    ->options([
                                        'daily' => 'Diario',
                                        'weekly' => 'Semanal',
                                        'monthly' => 'Mensual',
                                        'quarterly' => 'Trimestral',
                                    ])
                                    ->required()
                                    ->live(),

                                Forms\Components\TimePicker::make('schedule_time')
                                    ->label('Hora de Ejecución')
                                    ->required()
                                    ->default('09:00'),

                                Forms\Components\Select::make('schedule_day_of_week')
                                    ->label('Día de la Semana')
                                    ->options([
                                        0 => 'Domingo',
                                        1 => 'Lunes',
                                        2 => 'Martes',
                                        3 => 'Miércoles',
                                        4 => 'Jueves',
                                        5 => 'Viernes',
                                        6 => 'Sábado',
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('schedule_frequency') === 'weekly'),
                            ]),

                        Forms\Components\Select::make('schedule_day_of_month')
                            ->label('Día del Mes')
                            ->options(array_combine(range(1, 31), range(1, 31)))
                            ->visible(fn (Forms\Get $get) => in_array($get('schedule_frequency'), ['monthly', 'quarterly']))
                            ->default(1),
                    ]),

                Section::make('Destinatarios de Email')
                    ->schema([
                        Forms\Components\TagsInput::make('email_recipients')
                            ->label('Emails de Destinatarios')
                            ->placeholder('Escriba un email y presione Enter')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Desactive para pausar la ejecución del reporte'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('schedule_frequency')
                    ->label('Frecuencia')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'daily' => 'Diario',
                        'weekly' => 'Semanal',
                        'monthly' => 'Mensual',
                        'quarterly' => 'Trimestral',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'daily',
                        'info' => 'weekly',
                        'warning' => 'monthly',
                        'danger' => 'quarterly',
                    ]),

                Tables\Columns\TextColumn::make('schedule_time')
                    ->label('Hora')
                    ->time('H:i'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Última Ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca'),

                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Próxima Ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('No programada'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('schedule_frequency')
                    ->label('Frecuencia')
                    ->options([
                        'daily' => 'Diario',
                        'weekly' => 'Semanal',
                        'monthly' => 'Mensual',
                        'quarterly' => 'Trimestral',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),

                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('run_now')
                        ->label('Ejecutar Ahora')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function (ScheduledReport $record) {
                            // Trigger immediate execution
                            \App\Jobs\ProcessScheduledReports::dispatch();

                            \Filament\Notifications\Notification::make()
                                ->title('Reporte en Proceso')
                                ->body('El reporte se está generando y será enviado por email.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledReports::route('/'),
            'create' => Pages\CreateScheduledReport::route('/create'),
            'view' => Pages\ViewScheduledReport::route('/{record}'),
            'edit' => Pages\EditScheduledReport::route('/{record}/edit'),
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
